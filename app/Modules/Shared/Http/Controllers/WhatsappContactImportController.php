<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Segment;
use App\Modules\Shared\Services\ContactService;
use App\Modules\Whatsapp\Services\EvolutionGoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappContactImportController extends Controller
{
    public function __construct(private ContactService $contactService) {}

    public function index(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);

        return Inertia::render('Contacts/WhatsappImport', [
            'accounts' => ChannelAccount::query()
                ->where('workspace_id', $workspaceId)
                ->where('channel', 'whatsapp')
                ->where('provider', 'evolution_go')
                ->where('status', 'active')
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'status', 'meta_json'])
                ->map(fn (ChannelAccount $account) => [
                    'id' => $account->id,
                    'name' => $account->display_name ?: 'WhatsApp por QR Code',
                    'phone' => data_get($account->meta_json, 'phone')
                        ?: data_get($account->meta_json, 'owner_jid'),
                    'status' => $account->status,
                ])->values(),
            'tags' => ContactTag::where('workspace_id', $workspaceId)
                ->orderBy('name')->get(['id', 'name', 'color']),
            'segments' => Segment::where('workspace_id', $workspaceId)
                ->where('type', 'static')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_account_id' => ['required', 'integer'],
            'only_groups' => ['sometimes', 'boolean'],
            'group_jids' => ['required_if:only_groups,true', 'array', 'min:1', 'max:500'],
            'group_jids.*' => ['required', 'string', 'max:191', 'distinct'],
        ]);
        $workspaceId = $this->workspaceId($request);
        $account = $this->account($workspaceId, (int) $validated['channel_account_id']);
        $onlyGroups = (bool) ($validated['only_groups'] ?? false);
        $client = EvolutionGoClient::forAccount($account);
        $normalised = $onlyGroups
            ? $this->normaliseGroupParticipants($this->selectGroups($client->listGroups(), $validated['group_jids'] ?? []))
            : $this->normalise($client->listContacts());

        $existingPhones = Contact::withTrashed()
            ->where('workspace_id', $workspaceId)
            ->whereIn('phone_e164', array_column($normalised, 'phone_e164'))
            ->pluck('phone_e164')
            ->flip();

        $contacts = array_map(function (array $contact) use ($existingPhones) {
            $contact['status'] = $existingPhones->has($contact['phone_e164']) ? 'existing' : 'new';

            return $contact;
        }, $normalised);

        return response()->json([
            'contacts' => $contacts,
            'summary' => [
                'total' => count($contacts),
                'new' => count(array_filter($contacts, fn (array $item) => $item['status'] === 'new')),
                'existing' => count(array_filter($contacts, fn (array $item) => $item['status'] === 'existing')),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $validated = $request->validate([
            'channel_account_id' => ['required', 'integer'],
            'selected_jids' => ['required', 'array', 'min:1', 'max:5000'],
            'selected_jids.*' => ['required', 'string', 'max:191', 'distinct'],
            'only_groups' => ['sometimes', 'boolean'],
            'group_jids' => ['required_if:only_groups,true', 'array', 'min:1', 'max:500'],
            'group_jids.*' => ['required', 'string', 'max:191', 'distinct'],
            'update_existing_names' => ['sometimes', 'boolean'],
            'marketing_consent_confirmed' => ['sometimes', 'boolean'],
            'tag_id' => [
                'nullable', 'integer',
                Rule::exists('contact_tags', 'id')->where('workspace_id', $workspaceId),
            ],
            'segment_id' => [
                'nullable', 'integer',
                Rule::exists('segments', 'id')->where(fn ($query) => $query
                    ->where('workspace_id', $workspaceId)->where('type', 'static')),
            ],
        ]);

        $account = $this->account($workspaceId, (int) $validated['channel_account_id']);
        $selected = array_fill_keys($validated['selected_jids'], true);
        $onlyGroups = (bool) ($validated['only_groups'] ?? false);
        $client = EvolutionGoClient::forAccount($account);
        $contacts = array_values(array_filter(
            $onlyGroups
                ? $this->normaliseGroupParticipants($this->selectGroups($client->listGroups(), $validated['group_jids'] ?? []))
                : $this->normalise($client->listContacts()),
            fn (array $contact) => isset($selected[$contact['jid']])
        ));

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => count($selected) - count($contacts)];
        $updateNames = (bool) ($validated['update_existing_names'] ?? false);
        $hasConsent = (bool) ($validated['marketing_consent_confirmed'] ?? false);

        DB::transaction(function () use ($contacts, $workspaceId, $account, $validated, $updateNames, $hasConsent, $onlyGroups, &$stats) {
            foreach ($contacts as $row) {
                $existing = Contact::withTrashed()
                    ->where('workspace_id', $workspaceId)
                    ->where('phone_e164', $row['phone_e164'])
                    ->first();

                $data = [
                    'phone_e164' => $row['phone_e164'],
                    'custom_fields' => array_merge($existing?->custom_fields ?? [], [
                        'evolution_go_jids' => array_values(array_unique(array_merge(
                            $existing?->custom_fields['evolution_go_jids'] ?? [],
                            [$row['jid']]
                        ))),
                        'whatsapp_contact_import_account_id' => $account->id,
                        'whatsapp_business_name' => $row['business_name'] ?: null,
                        'whatsapp_import_group_names' => $onlyGroups
                            ? array_values(array_unique(array_merge(
                                $existing?->custom_fields['whatsapp_import_group_names'] ?? [],
                                $row['group_names'] ?? []
                            )))
                            : ($existing?->custom_fields['whatsapp_import_group_names'] ?? []),
                    ]),
                ];

                if (! $existing || $updateNames || (! $existing->first_name && ! $existing->last_name)) {
                    [$firstName, $lastName] = $this->splitName($row['name']);
                    $data['first_name'] = $firstName;
                    $data['last_name'] = $lastName;
                }
                if (! $existing) {
                    $data['source'] = $onlyGroups
                        ? 'whatsapp_group_participant_import'
                        : 'whatsapp_contact_import';
                    $data['opt_in_whatsapp'] = $hasConsent;
                } elseif ($hasConsent) {
                    $data['opt_in_whatsapp'] = true;
                }

                $contact = $this->contactService->upsert($workspaceId, $data, false);
                $existing ? $stats['updated']++ : $stats['created']++;

                if (! empty($validated['tag_id'])) {
                    $contact->tags()->syncWithoutDetaching([(int) $validated['tag_id']]);
                }
                if (! empty($validated['segment_id'])) {
                    $contact->segments()->syncWithoutDetaching([(int) $validated['segment_id']]);
                }
            }

            if (! empty($validated['segment_id'])) {
                $segment = Segment::where('workspace_id', $workspaceId)
                    ->whereKey((int) $validated['segment_id'])->first();
                $segment?->update(['contact_count' => $segment->contacts()->count()]);
            }
        });

        return response()->json([
            'message' => "Importação concluída: {$stats['created']} novos, {$stats['updated']} atualizados e {$stats['skipped']} ignorados.",
            'stats' => $stats,
        ]);
    }

    public function groups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_account_id' => ['required', 'integer'],
        ]);
        $workspaceId = $this->workspaceId($request);
        $account = $this->account($workspaceId, (int) $validated['channel_account_id']);

        $groups = collect(EvolutionGoClient::forAccount($account)->listGroups())
            ->map(function (array $group): ?array {
                $jid = trim((string) ($group['JID'] ?? $group['Jid'] ?? $group['jid'] ?? $group['ID'] ?? $group['id'] ?? ''));
                if (! preg_match('/^[0-9]+@g\.us$/i', $jid)) {
                    return null;
                }

                $participants = $group['Participants'] ?? $group['participants'] ?? [];
                $count = is_array($participants)
                    ? count($participants)
                    : (int) ($group['ParticipantCount'] ?? $group['participantCount'] ?? 0);

                return [
                    'jid' => $jid,
                    'name' => trim((string) ($group['Name'] ?? $group['name'] ?? $group['Subject'] ?? $group['subject'] ?? '')) ?: 'Grupo do WhatsApp',
                    'participant_count' => $count,
                ];
            })
            ->filter()
            ->unique('jid')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['groups' => $groups]);
    }

    private function account(int $workspaceId, int $accountId): ChannelAccount
    {
        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($accountId)
            ->where('channel', 'whatsapp')
            ->where('provider', 'evolution_go')
            ->where('status', 'active')
            ->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawContacts
     * @return array<int, array{jid:string,phone_e164:string,name:string,business_name:string}>
     */
    private function normalise(array $rawContacts): array
    {
        $contacts = [];

        foreach ($rawContacts as $raw) {
            $jid = trim((string) ($raw['Jid'] ?? $raw['jid'] ?? ''));
            if (! preg_match('/^([0-9]{8,15})@(s\.whatsapp\.net|c\.us)$/i', $jid, $matches)) {
                continue;
            }
            if (array_key_exists('Found', $raw) && $raw['Found'] === false) {
                continue;
            }

            $phone = '+'.$matches[1];
            $businessName = trim((string) ($raw['BusinessName'] ?? $raw['businessName'] ?? ''));
            $name = trim((string) ($raw['FullName'] ?? $raw['fullName'] ?? ''))
                ?: trim((string) ($raw['PushName'] ?? $raw['pushName'] ?? ''))
                ?: $businessName
                ?: trim((string) ($raw['FirstName'] ?? $raw['firstName'] ?? ''))
                ?: $phone;

            $candidate = [
                'jid' => $jid,
                'phone_e164' => $phone,
                'name' => mb_substr($name, 0, 255),
                'business_name' => mb_substr($businessName, 0, 255),
            ];

            if (! isset($contacts[$phone]) || $contacts[$phone]['name'] === $phone) {
                $contacts[$phone] = $candidate;
            }
        }

        uasort($contacts, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

        return array_values($contacts);
    }

    /**
     * Flatten and deduplicate group participants without importing groups.
     * Participants without a phone-number JID are ignored.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array{jid:string,phone_e164:string,name:string,business_name:string,group_names:array<int,string>}>
     */
    private function normaliseGroupParticipants(array $groups): array
    {
        $contacts = [];

        foreach ($groups as $group) {
            $groupName = trim((string) ($group['Name'] ?? $group['name'] ?? $group['Subject'] ?? $group['subject'] ?? ''));
            $participants = $group['Participants'] ?? $group['participants'] ?? [];
            if (! is_array($participants)) {
                continue;
            }

            foreach ($participants as $participant) {
                if (! is_array($participant)) {
                    continue;
                }

                $phoneJid = trim((string) (
                    $participant['PhoneNumber'] ?? $participant['phoneNumber']
                    ?? $participant['JID'] ?? $participant['jid'] ?? ''
                ));
                if (preg_match('/^[0-9]{8,15}$/', $phoneJid)) {
                    $phoneJid .= '@s.whatsapp.net';
                }
                if (! preg_match('/^([0-9]{8,15})@(s\.whatsapp\.net|c\.us)$/i', $phoneJid, $matches)) {
                    continue;
                }

                $phone = '+'.$matches[1];
                $name = trim((string) (
                    $participant['DisplayName'] ?? $participant['displayName']
                    ?? $participant['PushName'] ?? $participant['pushName'] ?? ''
                )) ?: $phone;

                if (! isset($contacts[$phone])) {
                    $contacts[$phone] = [
                        'jid' => $phoneJid,
                        'phone_e164' => $phone,
                        'name' => mb_substr($name, 0, 255),
                        'business_name' => '',
                        'group_names' => [],
                    ];
                } elseif ($contacts[$phone]['name'] === $phone && $name !== $phone) {
                    $contacts[$phone]['name'] = mb_substr($name, 0, 255);
                }

                if ($groupName !== '' && ! in_array($groupName, $contacts[$phone]['group_names'], true)) {
                    $contacts[$phone]['group_names'][] = mb_substr($groupName, 0, 255);
                }
            }
        }

        uasort($contacts, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

        return array_values($contacts);
    }

    /** @param array<int, array<string, mixed>> $groups */
    private function selectGroups(array $groups, array $selectedJids): array
    {
        $selected = array_fill_keys($selectedJids, true);

        return array_values(array_filter($groups, function (array $group) use ($selected): bool {
            $jid = trim((string) ($group['JID'] ?? $group['Jid'] ?? $group['jid'] ?? $group['ID'] ?? $group['id'] ?? ''));

            return isset($selected[$jid]);
        }));
    }

    /** @return array{0:string|null,1:string|null} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2) ?: [];

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }
}
