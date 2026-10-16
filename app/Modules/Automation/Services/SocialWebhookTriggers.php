<?php

namespace App\Modules\Automation\Services;

use App\Modules\Integrations\Models\ZernioAccountMapping;
use App\Modules\Integrations\Models\ZernioWorkspaceConnection;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;

class SocialWebhookTriggers
{
    public function __construct(private readonly SocialAutomationTriggers $triggers) {}

    public function zernio(ZernioWorkspaceConnection $connection, array $payload): void
    {
        if (! empty($payload['test'])) return;
        $event = (string) ($payload['event'] ?? '');
        $accountId = (string) data_get($payload, 'account.accountId', data_get($payload, 'account.id', ''));
        $targets = str_starts_with($event, 'post.') ? (array) data_get($payload, 'post.platforms', []) : [['accountId' => $accountId]];
        foreach ($targets as $target) {
            $mapping = ZernioAccountMapping::where('connection_id', $connection->id)
                ->where('workspace_id', $connection->workspace_id)->where('account_id', $target['accountId'] ?? '')->first();
            if (! $mapping) continue;
            $platform = $mapping->platform;
            $key = 'zernio:'.$mapping->account_id;
            $eventId = (string) ($payload['id'] ?? hash('sha256', json_encode($payload)));
            $context = [];
            $contactId = null;
            if ($event === 'comment.received') {
                if (data_get($payload, 'comment.author.isOwnAccount', false)) continue;
                $context = ['comment_id' => data_get($payload, 'comment.id'), 'comment_text' => data_get($payload, 'comment.text', ''),
                    'post_id' => data_get($payload, 'comment.platformPostId', data_get($payload, 'post.platformPostId')),
                    'author_id' => data_get($payload, 'comment.author.id')];
                $contactId = $this->actor($connection->workspace_id, $key, (array) data_get($payload, 'comment.author', []));
            } elseif (in_array($event, ['review.new', 'review.updated'], true)) {
                $context = ['review_id' => data_get($payload, 'review.id'), 'review_text' => data_get($payload, 'review.text', ''),
                    'review_rating' => data_get($payload, 'review.rating')];
            } elseif (in_array($event, ['post.published', 'post.failed', 'post.partial'], true)) {
                $status = $target['status'] ?? '';
                if (! in_array($status, ['published', 'failed'], true)) continue;
                $eventForTarget = $status === 'published' ? 'post.published' : 'post.failed';
                $context = ['post_id' => $target['platformPostId'] ?? data_get($payload, 'post.id'),
                    'post_content' => data_get($payload, 'post.content', ''), 'post_url' => $target['publishedUrl'] ?? '',
                    'post_error' => $target['error'] ?? ''];
                // A platform target is terminal once, even if several post status callbacks arrive.
                $eventId = (string) data_get($payload, 'post.id', $eventId).':'.$status;
            } elseif ($event === 'message.received' && ! in_array($platform, ['facebook', 'instagram', 'telegram', 'whatsapp', 'sms'])) {
                if (data_get($payload, 'message.metadata.standby', false) || data_get($payload, 'metadata.standby', false)) continue;
                $context = ['message_body' => data_get($payload, 'message.text', ''), 'message_channel' => $platform,
                    'external_conversation_id' => data_get($payload, 'conversation.id')];
                $contactId = $this->actor($connection->workspace_id, $key, (array) data_get($payload, 'message.sender', []));
            } else {
                continue;
            }
            $this->triggers->fire($connection->workspace_id, $platform, $eventForTarget ?? $event, $key, $eventId, $context, $contactId);
            unset($eventForTarget);
        }
    }

    public function meta(array $payload, string $object): void
    {
        $platform = $object === 'instagram' ? 'instagram' : 'facebook';
        foreach ($payload['entry'] ?? [] as $entry) {
            $id = (string) ($entry['id'] ?? '');
            if ($id === '') continue;
            $accounts = ChannelAccount::where('channel', $platform === 'facebook' ? 'messenger' : 'instagram')
                ->where('provider', '!=', 'zernio')->where(function ($query) use ($id, $platform) {
                    if ($platform === 'facebook') $query->where('meta_json->page_id', $id);
                    else $query->where('meta_json->instagram_account_id', $id)->orWhere('meta_json->instagram_page_id', $id);
                })->get();
            foreach ($entry['changes'] ?? [] as $change) {
                $value = (array) ($change['value'] ?? []);
                $isComment = $platform === 'instagram' && ($change['field'] ?? '') === 'comments'
                    || $platform === 'facebook' && ($change['field'] ?? '') === 'feed'
                        && ($value['item'] ?? '') === 'comment' && ($value['verb'] ?? '') === 'add';
                if (! $isComment) continue;
                $commentId = (string) ($value['comment_id'] ?? $value['id'] ?? '');
                $author = (array) ($value['from'] ?? []);
                if ($commentId === '' || (string) ($author['id'] ?? '') === $id) continue;
                foreach ($accounts as $account) {
                    $key = 'channel:'.$account->id;
                    $context = ['comment_id' => $commentId, 'comment_text' => $value['text'] ?? $value['message'] ?? '',
                        'post_id' => $value['post_id'] ?? data_get($value, 'media.id'), 'author_id' => $author['id'] ?? null];
                    $this->triggers->fire($account->workspace_id, $platform, 'comment.received', $key, $commentId, $context,
                        $this->actor($account->workspace_id, $key, $author));
                }
            }
        }
    }

    private function actor(int $workspaceId, string $accountKey, array $author): ?int
    {
        $id = (string) ($author['businessScopedUserId'] ?? $author['id'] ?? '');
        if ($id === '') return null;
        $key = hash('sha256', $accountKey.':'.$id);
        $contact = Contact::where('workspace_id', $workspaceId)->where('custom_fields->social_actor_key', $key)->first();
        return ($contact ?? Contact::create(['workspace_id' => $workspaceId, 'source' => 'social_event',
            'first_name' => $author['name'] ?? $author['username'] ?? 'Contato social',
            'custom_fields' => ['social_actor_key' => $key, 'social_account' => $accountKey, 'social_author_id' => $id],
        ]))->id;
    }
}
