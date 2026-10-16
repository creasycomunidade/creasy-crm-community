<?php

namespace App\Modules\Shared\Services;

use App\Events\ContactCreated;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Segment;
use App\Services\StorageManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ContactService
{
    public function __construct(private StorageManager $storageManager) {}
    /**
     * Upsert a contact by phone (E.164) within a workspace.
     * Falls back to email lookup if phone is absent.
     *
     * @param  bool  $dispatchCreatedEvent  Set false for bulk imports/syncs to avoid
     *                                       firing contact.created automations + outbound
     *                                       webhooks for thousands of historical records.
     */
    public function upsert(int $workspaceId, array $data, bool $dispatchCreatedEvent = true): Contact
    {
        $lookup = [];

        if (! empty($data['phone_e164'])) {
            $lookup = ['workspace_id' => $workspaceId, 'phone_e164' => $data['phone_e164']];
        } elseif (! empty($data['email'])) {
            $lookup = ['workspace_id' => $workspaceId, 'email' => $data['email']];
        }

        if (empty($lookup)) {
            $contact = Contact::create(array_merge($data, ['workspace_id' => $workspaceId]));
            if ($dispatchCreatedEvent) {
                ContactCreated::dispatch($contact);
            }

            return $contact;
        }

        $exists = Contact::withTrashed()->where($lookup)->exists();
        $contact = Contact::withTrashed()->updateOrCreate($lookup, array_merge($data, ['workspace_id' => $workspaceId]));

        // Restore soft-deleted contact so it appears in normal queries again.
        if ($contact->trashed()) {
            $contact->restore();
        }

        if (! $exists && $dispatchCreatedEvent) {
            ContactCreated::dispatch($contact);
        }

        return $contact;
    }

    /**
     * Bulk import from an array of rows.
     * Returns ['created' => int, 'updated' => int, 'skipped' => int].
     */
    public function bulkImport(int $workspaceId, array $rows, string $source = 'import'): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            try {
                $existing = Contact::where('workspace_id', $workspaceId)
                    ->when(! empty($row['phone_e164']), fn ($q) => $q->where('phone_e164', $row['phone_e164']))
                    ->orWhere(fn ($q) => $q->where('workspace_id', $workspaceId)->where('email', $row['email'] ?? '__none__'))
                    ->first();

                $contact = $this->upsert($workspaceId, array_merge(['source' => $source], $row));

                if ($existing) {
                    $stats['updated']++;
                } else {
                    $stats['created']++;
                }
            } catch (\Throwable) {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * Import contacts from spreadsheet-style rows (E.164 phone required per row).
     *
     * @param  array<int, array{name?: string|null, phone_e164?: string|null, tag_id?: int|null, segment_id?: int|null}>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importGridRows(int $workspaceId, array $rows, string $source = 'import'): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $segmentIdsTouched = [];

        foreach ($rows as $row) {
            $phone = isset($row['phone_e164']) ? trim((string) $row['phone_e164']) : '';
            if ($phone === '' || ! str_starts_with($phone, '+')) {
                $stats['skipped']++;

                continue;
            }

            try {
                $existing = Contact::where('workspace_id', $workspaceId)
                    ->where('phone_e164', $phone)
                    ->first();

                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                $firstName = null;
                $lastName = null;
                if ($name !== '') {
                    $parts = preg_split('/\s+/u', $name, 2) ?: [];
                    $firstName = $parts[0] ?? null;
                    $lastName = $parts[1] ?? null;
                }

                $contact = $this->upsert($workspaceId, [
                    'phone_e164' => $phone,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'source' => $source,
                ]);

                if ($existing) {
                    $stats['updated']++;
                } else {
                    $stats['created']++;
                }

                $tagId = isset($row['tag_id']) ? (int) $row['tag_id'] : 0;
                if ($tagId > 0 && ContactTag::where('workspace_id', $workspaceId)->whereKey($tagId)->exists()) {
                    $contact->tags()->syncWithoutDetaching([$tagId]);
                }

                $segmentId = isset($row['segment_id']) ? (int) $row['segment_id'] : 0;
                if ($segmentId > 0) {
                    $segment = Segment::where('workspace_id', $workspaceId)
                        ->whereKey($segmentId)
                        ->where('type', 'static')
                        ->first();
                    if ($segment) {
                        $contact->segments()->syncWithoutDetaching([$segment->id]);
                        $segmentIdsTouched[$segment->id] = true;
                    }
                }
            } catch (\Throwable) {
                $stats['skipped']++;
            }
        }

        foreach (array_keys($segmentIdsTouched) as $segmentId) {
            $segment = Segment::query()->find($segmentId);
            if ($segment) {
                $segment->update(['contact_count' => $segment->contacts()->count()]);
            }
        }

        return $stats;
    }

    /**
     * Sync a contact's avatar from an external URL (WhatsApp, Instagram, Messenger profile pics).
     * Only updates if the contact has no manually-uploaded avatar (non-http stored path),
     * or if force=true.
     */
    public function syncAvatarFromUrl(Contact $contact, string $url, bool $force = false): void
    {
        if (! $force && $contact->avatar && ! str_starts_with($contact->avatar, 'http')) {
            // Contact has a manually uploaded avatar — don't overwrite
            return;
        }

        // Store the external URL directly (lightweight — no download needed for display)
        if ($contact->avatar !== $url) {
            $contact->update(['avatar' => $url]);
        }
    }

    /**
     * Download an external avatar URL and store it locally on the public disk.
     * Use this when you need a permanent local copy (e.g. WhatsApp CDN URLs expire).
     */
    public function downloadAndStoreAvatar(Contact $contact, string $url): void
    {
        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                return;
            }

            $contentType = $response->header('Content-Type') ?? 'image/jpeg';
            $ext = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'gif') => 'gif',
                default => 'jpg',
            };

            // Delete old stored avatar
            if ($contact->avatar && ! str_starts_with($contact->avatar, 'http')) {
                $this->storageManager->disk()->delete($contact->avatar);
            }

            $rawPath = 'contact-avatars/'.$contact->id.'_'.time().'.'.$ext;
            $path = $this->storageManager->prefixedPath($rawPath);
            $this->storageManager->disk()->put($path, $response->body());
            $contact->update(['avatar' => $path]);
        } catch (\Throwable) {
            // Avatar sync is non-critical; silently fail
        }
    }

    /** Export contacts for a workspace as array of arrays. */
    public function export(int $workspaceId): Collection
    {
        return Contact::where('workspace_id', $workspaceId)
            ->with('tags')
            ->get()
            ->map(fn (Contact $c) => [
                'phone' => $c->phone_e164,
                'email' => $c->email,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'country' => $c->country,
                'language' => $c->language,
                'opt_in_wa' => $c->opt_in_whatsapp ? 'yes' : 'no',
                'opt_in_sms' => $c->opt_in_sms ? 'yes' : 'no',
                'opt_in_email' => $c->opt_in_email ? 'yes' : 'no',
                'tags' => $c->tags->pluck('name')->implode(','),
                'source' => $c->source,
                'created_at' => $c->created_at?->toISOString(),
            ]);
    }
}
