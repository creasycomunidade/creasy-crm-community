<?php

namespace App\Modules\Automation\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiMemoryNode;
use App\Modules\AI\Services\ChatbotRunner;
use App\Modules\AI\Services\LlmGateway;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Ecommerce\Models\EcommerceOrder;
use App\Modules\FazOS\Models\FazOSContactLink;
use App\Modules\FazOS\Models\FazOSServiceOrder;
use App\Modules\Integrations\Models\ZernioAccountMapping;
use App\Modules\Integrations\Models\ZernioWorkspaceConnection;
use App\Modules\Integrations\Services\ZernioClient;
use App\Modules\Leads\Models\Deal;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\PipelineStage;
use App\Modules\Leads\Models\TaskCard;
use App\Modules\Leads\Models\TaskColumn;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Segment;
use App\Modules\Social\Models\SocialAccount;
use App\Modules\Social\Models\SocialPost;
use App\Modules\Social\Services\SocialPublisher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ExtendedAutomationNodes
{
    public static function catalog(): array
    {
        static $catalog;

        return $catalog ??= json_decode(file_get_contents(resource_path('js/Pages/Automation/nodeCatalog.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function validate(string $type, array $data): void
    {
        $rules = [];
        foreach (self::catalog()[$type]['fields'] ?? [] as $field) {
            $rules[$field['key']] = [empty($field['required']) ? 'nullable' : 'required'];
            if ($field['type'] === 'number') {
                $rules[$field['key']][] = 'numeric';
                if (isset($field['min'])) {
                    $rules[$field['key']][] = 'min:'.$field['min'];
                }
                if (isset($field['max'])) {
                    $rules[$field['key']][] = 'max:'.$field['max'];
                }
            } elseif ($field['type'] === 'resource') {
                $rules[$field['key']][] = 'integer';
            } else {
                $rules[$field['key']][] = 'string';
                $rules[$field['key']][] = 'max:10000';
            }
        }
        if (isset($data['variable'])) {
            $rules['variable'] = ['required', 'regex:/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/'];
        }
        Validator::make($data, $rules)->validate();
    }

    public function execute(string $type, array $data, AutomationRun $run, array $context): array
    {
        $wid = (int) $run->automation->workspace_id;
        $module = match ($type) {
            'ai_generate','knowledge_answer' => 'ai', 'save_memory' => 'second_brain',
            'create_task','create_deal','move_deal' => 'crm','lookup_lead' => 'leads',
            'social_draft','publish_social_post' => 'social','ads_summary' => 'ads','lookup_order' => 'ecommerce',
            default => null,
        };
        if ($module && ! \App\Support\CommunityEdition::allows($module)) {
            throw new RuntimeException('O módulo necessário para este nó não está disponível: '.$module);
        }
        $contact = Contact::where('workspace_id', $wid)->find($run->contact_id);
        $render = fn ($s) => AutomationVariables::render((string) $s, $contact, $context);
        self::validate($type, $data);
        $ok = fn ($message, $values = []) => ['status' => 'ok', 'message' => $message, 'context_update' => $values];
        if ($type === 'set_variable') {
            return $ok('Variável atualizada.', [$data['variable'] => $render($data['value'] ?? '')]);
        }
        if ($type === 'random_split') {
            return ['status' => 'ok', 'message' => 'Ramo A/B selecionado.', 'branch' => random_int(1, 100) <= (int) ($data['percentage'] ?? 50) ? 'true' : 'false'];
        }
        if ($type === 'stop_flow') {
            return ['status' => 'ok', 'message' => 'Fluxo encerrado.', 'stop' => true];
        }
        if (in_array($type, ['ai_generate', 'knowledge_answer'], true)) {
            $prompt = $render($data['prompt']);
            if ($type === 'knowledge_answer') {
                $bot = AiChatbot::where('workspace_id', $wid)->where('enabled', true)->findOrFail($data['chatbot_id']);
                $result = app(ChatbotRunner::class)->runForApi($bot, $prompt, $wid, []);
                $answer = $result['reply'] ?? '';
            } else {
                $answer = app(LlmGateway::class)->chat($wid, [['role' => 'user', 'content' => $prompt]], ['max_tokens' => 1024])->content;
            }

            return $ok('Resposta da IA salva na variável.', [$data['variable'] => $answer]);
        }
        if ($type === 'ads_summary') {
            $connection = ZernioWorkspaceConnection::where('workspace_id', $wid)->firstOrFail();
            $result = app(ZernioClient::class)->ads($connection->profile_id);
            // Keep execution context bounded; do not expose provider credentials.
            $rows = collect($result['ads'] ?? $result['data'] ?? [])->take(100)->map(fn ($row) => array_intersect_key((array) $row, array_flip(['id', 'name', 'status', 'platform', 'budget', 'spend'])))->values()->all();

            return $ok('Anúncios consultados.', ['ads' => ['items' => $rows, 'count' => count($rows)]]);
        }
        if (in_array($type, ['reply_comment', 'private_reply', 'reply_review', 'social_message'], true)) {
            $key = (string) ($context['trigger_account'] ?? '');
            if (! str_starts_with($key, 'zernio:')) {
                throw new RuntimeException('Este nó requer um gatilho de uma conta Zernio conectada.');
            }
            $mapping = ZernioAccountMapping::where('workspace_id', $wid)->where('account_id', substr($key, 7))->where('status', 'connected')->firstOrFail();
            $body = $render($data['body']);
            if (trim($body) === '') {
                throw new RuntimeException('A mensagem ficou vazia após substituir as variáveis.');
            }
            $post = (string) ($context['post_id'] ?? '');
            $comment = (string) ($context['comment_id'] ?? '');
            $idempotency = hash('sha256', 'automation:'.$run->id.':'.$run->current_node_id);
            $client = app(ZernioClient::class);
            if ($type === 'reply_review') {
                if ($mapping->platform !== 'googlebusiness' || empty($context['review_id'])) {
                    throw new RuntimeException('Use este nó após um gatilho de avaliação do Google Business.');
                }
                $result = $client->automationReply('review', (string) $context['review_id'], '', $mapping->account_id, $body, $idempotency);
            } elseif ($type === 'social_message') {
                if (! in_array($mapping->platform, ['twitter', 'reddit', 'bluesky', 'tiktok'], true) || empty($context['external_conversation_id'])) {
                    throw new RuntimeException('Este nó precisa de uma mensagem recebida no X, Reddit, Bluesky ou TikTok.');
                }
                $result = $client->sendMessage((string) $context['external_conversation_id'], ['accountId' => $mapping->account_id, 'message' => $body], $idempotency);
            } else {
                if ($post === '' || $comment === '') {
                    throw new RuntimeException('Use este nó após um comentário recebido com IDs de comentário e publicação.');
                }
                if ($type === 'private_reply' && ! in_array($mapping->platform, ['instagram', 'facebook'], true)) {
                    throw new RuntimeException('Resposta privada a comentário está disponível apenas no Instagram e Facebook.');
                }
                // A consumed private reply cannot safely be retried, even in another execution.
                $once = 'automation-private-reply:'.$wid.':'.hash('sha256', $key.':'.$comment);
                if ($type === 'private_reply' && ! Cache::add($once, 'attempted', now()->addDays(8))) {
                    throw new RuntimeException('Já houve uma tentativa de Direct para este comentário. Confira a entrega antes de repetir.');
                }
                $result = $client->automationReply($type === 'private_reply' ? 'private' : 'comment', $post, $comment, $mapping->account_id, $body, $idempotency);
            }

            return $ok('Resposta social enviada.', ['social_response_id' => data_get($result, 'data.commentId', $result['messageId'] ?? '')]);
        }
        if ($type === 'save_memory') {
            $memory = AiMemoryNode::create(['workspace_id' => $wid, 'node_type' => 'document', 'title' => $render($data['title']), 'content' => $render($data['body']), 'summary' => 'Nota criada por automação.', 'source_type' => 'automation', 'source_id' => $run->id.':'.substr(hash('sha256', (string) $run->current_node_id), 0, 16), 'importance' => 3, 'confidence' => 1, 'status' => 'active']);

            return $ok('Nota salva no Segundo Cérebro.', ['memory_id' => $memory->id]);
        }
        if ($type === 'publish_social_post') {
            $post = SocialPost::where('workspace_id', $wid)->findOrFail($data['social_post_id']);
            $lock = Cache::lock('automation-social-post:'.$post->id, 150);
            if (! $lock->get()) {
                throw new RuntimeException('Esta publicação já está sendo processada.');
            }
            try {
                $post->refresh();
                if ($post->status !== 'draft') {
                    throw new RuntimeException('Somente um rascunho ainda não publicado pode ser enviado por este nó.');
                }
                app(SocialPublisher::class)->publish($post);
                $post->refresh();
                if ($post->status !== 'published' || collect($post->publish_results ?? [])->contains(fn ($r) => ($r['status'] ?? '') !== 'published')) {
                    throw new RuntimeException('A publicação não foi concluída. Consulte os detalhes no módulo Social.');
                }
            } finally {
                $lock->release();
            }

            return $ok('Rascunho publicado.', ['social_post_id' => $post->id]);
        }
        if ($type === 'social_draft') {
            $account = SocialAccount::where('workspace_id', $wid)->findOrFail($data['social_account_id']);
            $post = SocialPost::create(['workspace_id' => $wid, 'title' => $render($data['title']), 'body' => $render($data['body']), 'status' => 'draft', 'target_accounts' => [$account->id], 'media_urls' => empty($data['media_url']) ? [] : [$render($data['media_url'])]]);
            $post->accounts()->sync([$account->id]);

            return $ok('Rascunho criado no módulo Social.', ['social_post_id' => $post->id]);
        }
        if ($type === 'create_task') {
            $column = TaskColumn::where('workspace_id', $wid)->findOrFail($data['column_id']);
            $user = null;
            if (! empty($data['assigned_user_id'])) {
                $user = User::forWorkspace($wid)->findOrFail($data['assigned_user_id']);
            }
            $task = TaskCard::create(['workspace_id' => $wid, 'board_id' => $column->board_id, 'column_id' => $column->id, 'title' => $render($data['title']), 'description' => $render($data['description'] ?? ''), 'assigned_user_id' => $user?->id, 'priority' => 'normal', 'position' => 0]);

            return $ok('Tarefa criada.', ['task_id' => $task->id]);
        }
        if (in_array($type, ['create_deal', 'move_deal'], true)) {
            if (! $contact) {
                throw new RuntimeException('Esta ação precisa de um contato.');
            }
            $stage = PipelineStage::where('workspace_id', $wid)->findOrFail($data['stage_id']);
            if ($type === 'create_deal') {
                $deal = Deal::create(['workspace_id' => $wid, 'pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id, 'contact_id' => $contact->id, 'title' => $render($data['title']), 'value_cents' => (int) round((float) ($data['value'] ?? 0) * 100), 'currency' => 'BRL', 'position' => 0]);
            } else {
                $deal = Deal::where('workspace_id', $wid)->where('contact_id', $contact->id)->findOrFail($render($data['deal_id']));
                $deal->update(['pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id]);
            }

            return $ok('Oportunidade atualizada.', ['deal_id' => $deal->id]);
        }
        if (! $contact) {
            throw new RuntimeException('Esta ação precisa de um contato.');
        }
        if (in_array($type, ['add_segment', 'remove_segment'], true)) {
            $segment = Segment::where('workspace_id', $wid)->where('type', 'static')->findOrFail($data['segment_id']);
            if ($type === 'add_segment') {
                $segment->contacts()->syncWithoutDetaching([$contact->id]);
            } else {
                $segment->contacts()->detach($contact->id);
            }
            $segment->update(['contact_count' => $segment->contacts()->count()]);

            return $ok('Segmento atualizado.');
        }
        if ($type === 'conversation_status') {
            if (! in_array($data['status'] ?? '', ['open', 'resolved'], true)) {
                throw new RuntimeException('Status inválido.');
            }
            $conversation = Conversation::where('workspace_id', $wid)->where('contact_id', $contact->id)->findOrFail($context['conversation_id'] ?? 0);
            $conversation->update(['status' => $data['status'], 'resolved_at' => $data['status'] === 'resolved' ? now() : null]);

            return $ok('Status da conversa atualizado.');
        }
        if ($type === 'lookup_service_order') {
            $links = FazOSContactLink::where('workspace_id', $wid)->where('contact_id', $contact->id)->pluck('fazos_customer_id');
            $order = FazOSServiceOrder::where('workspace_id', $wid)->whereIn('fazos_customer_id', $links)->latest('opened_at')->first();

            return $ok('Consulta de OS concluída.', ['service_order' => $order?->only(['number', 'status', 'title', 'total', 'currency', 'tracking_number']) ?? [], 'service_order_found' => (bool) $order]);
        }
        if ($type === 'lookup_order') {
            $order = EcommerceOrder::where('workspace_id', $wid)->where('contact_id', $contact->id)->latest('placed_at')->first();

            return $ok('Consulta de pedido concluída.', ['order' => $order?->only(['id', 'number', 'status', 'financial_status', 'fulfillment_status', 'total', 'currency', 'tracking_url']) ?? [], 'order_found' => (bool) $order]);
        }
        if ($type === 'lookup_lead') {
            $lead = $contact->email ? Lead::where('workspace_id', $wid)->where('email', $contact->email)->first() : null;

            return $ok('Consulta de lead concluída.',['lead' => $lead?->only(['id', 'name', 'email', 'city', 'country', 'category', 'rating']) ?? [], 'lead_found' => (bool) $lead]);
        }
        throw new RuntimeException('Nó não implementado: '.$type);
    }
}
