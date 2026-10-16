<?php

namespace App\Console\Commands;

use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairEvolutionGoConversationsCommand extends Command
{
    protected $signature = 'inbox:repair-evolution-conversations
        {--workspace= : Limitar a um espaço de trabalho}
        {--apply : Aplicar a consolidação; sem esta opção apenas exibe o diagnóstico}';

    protected $description = 'Consolida conversas duplicadas do WhatsApp não oficial usando número e LID';

    public function handle(): int
    {
        $accounts = ChannelAccount::query()
            ->where('provider', 'evolution_go')
            ->when($this->option('workspace'), fn ($query, $workspace) => $query->where('workspace_id', $workspace))
            ->get();
        $apply = (bool) $this->option('apply');
        $groupsFound = 0;
        $conversationsMerged = 0;

        foreach ($accounts as $account) {
            $conversations = Conversation::query()
                ->where('workspace_id', $account->workspace_id)
                ->where('channel_account_id', $account->id)
                ->with(['contact', 'messages' => fn ($query) => $query->orderBy('id')])
                ->orderBy('id')
                ->get();

            $groups = $conversations
                ->mapToGroups(fn (Conversation $conversation) => [
                    $this->canonicalIdentity($conversation) => $conversation,
                ])
                ->filter(fn (Collection $group, string $identity) => $identity !== '' && $group->count() > 1);

            foreach ($groups as $identity => $group) {
                $groupsFound++;
                $this->line(sprintf(
                    '%s conta #%d: %d caixas para %s',
                    $apply ? 'Consolidando' : 'Encontrado',
                    $account->id,
                    $group->count(),
                    $identity,
                ));

                if (! $apply) {
                    continue;
                }

                DB::transaction(function () use ($account, $identity, $group, &$conversationsMerged) {
                    $targetContact = $this->targetContact((int) $account->workspace_id, $identity, $group);
                    $target = $group->first(
                        fn (Conversation $conversation) => (int) $conversation->contact_id === (int) $targetContact->id
                    ) ?? $group->first();
                    $aliases = $group
                        ->flatMap(fn (Conversation $conversation) => $this->conversationJids($conversation))
                        ->unique()
                        ->values()
                        ->all();

                    $customFields = array_merge($targetContact->custom_fields ?? [], [
                        'evolution_go_jids' => array_values(array_unique(array_merge(
                            $targetContact->custom_fields['evolution_go_jids'] ?? [],
                            $aliases,
                        ))),
                    ]);
                    $targetContact->update(['custom_fields' => $customFields]);
                    $target->update([
                        'contact_id' => $targetContact->id,
                        'external_thread_id' => $identity,
                        'last_message_at' => $group->max('last_message_at'),
                        'last_inbound_at' => $group->max('last_inbound_at'),
                        'unread_count' => $group->sum('unread_count'),
                    ]);

                    foreach ($group as $duplicate) {
                        if ((int) $duplicate->id === (int) $target->id) {
                            continue;
                        }

                        DB::table('messages')
                            ->where('conversation_id', $duplicate->id)
                            ->update(['conversation_id' => $target->id]);
                        foreach (['inbox_notes', 'internal_notes', 'ai_runs'] as $table) {
                            if (Schema::hasTable($table)) {
                                DB::table($table)
                                    ->where('conversation_id', $duplicate->id)
                                    ->update(['conversation_id' => $target->id]);
                            }
                        }
                        $this->mergePivot('inbox_label_conversation', 'label_id', $duplicate->id, $target->id);
                        $this->mergePivot('inbox_assignments', 'user_id', $duplicate->id, $target->id);
                        $duplicate->delete();
                        $conversationsMerged++;
                    }
                });
            }
        }

        if ($groupsFound === 0) {
            $this->info('Nenhuma conversa duplicada do WhatsApp não oficial foi encontrada.');
        } elseif ($apply) {
            $this->info("Concluído: {$conversationsMerged} caixa(s) duplicada(s) consolidada(s).");
        } else {
            $this->warn("{$groupsFound} grupo(s) duplicado(s). Execute novamente com --apply para consolidar.");
        }

        return self::SUCCESS;
    }

    private function canonicalIdentity(Conversation $conversation): string
    {
        $jids = $this->conversationJids($conversation);
        foreach ($jids as $jid) {
            if (str_ends_with($jid, '@s.whatsapp.net')) {
                return $jid;
            }
        }

        return $jids[0] ?? '';
    }

    /** @return array<int, string> */
    private function conversationJids(Conversation $conversation): array
    {
        $values = [$conversation->external_thread_id];
        foreach ($conversation->messages as $message) {
            $data = $message->payload['evolution_go'] ?? [];
            $info = is_array($data['Info'] ?? null)
                ? $data['Info']
                : (is_array($data['info'] ?? null) ? $data['info'] : []);
            array_push(
                $values,
                $info['Chat'] ?? $info['chat'] ?? null,
                $info['Sender'] ?? $info['sender'] ?? null,
                $info['SenderAlt'] ?? $info['senderAlt'] ?? null,
            );
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($value) => $this->normaliseJid($value),
            $values,
        ))));
    }

    private function normaliseJid(mixed $value): ?string
    {
        if (is_array($value)) {
            $user = $value['User'] ?? $value['user'] ?? null;
            $server = $value['Server'] ?? $value['server'] ?? null;
            $value = $user && $server ? $user.'@'.$server : null;
        }
        if (! is_string($value) || ! str_contains($value, '@')) {
            return null;
        }

        [$user, $server] = array_pad(explode('@', trim($value), 2), 2, '');
        $user = preg_replace('/:\d+$/', '', ltrim($user, '+')) ?: '';
        $server = strtolower($server);

        return ctype_digit($user) && in_array($server, ['s.whatsapp.net', 'lid'], true)
            ? $user.'@'.$server
            : null;
    }

    /** @param Collection<int, Conversation> $group */
    private function targetContact(int $workspaceId, string $identity, Collection $group): Contact
    {
        if (str_ends_with($identity, '@s.whatsapp.net')) {
            $phone = '+'.explode('@', $identity, 2)[0];
            $byPhone = Contact::withTrashed()
                ->where('workspace_id', $workspaceId)
                ->where('phone_e164', $phone)
                ->first();
            if ($byPhone) {
                if ($byPhone->trashed()) {
                    $byPhone->restore();
                }

                return $byPhone;
            }

            $contact = $group->first()->contact;
            if ($contact && ! Contact::where('workspace_id', $workspaceId)->where('phone_e164', $phone)->exists()) {
                $contact->update(['phone_e164' => $phone]);
            }

            return $contact;
        }

        return $group->first()->contact;
    }

    private function mergePivot(string $table, string $valueColumn, int $from, int $to): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->where('conversation_id', $from)->get();
        foreach ($rows as $row) {
            $data = (array) $row;
            $data['conversation_id'] = $to;
            DB::table($table)->insertOrIgnore($data);
        }
        DB::table($table)->where('conversation_id', $from)->delete();
    }
}
