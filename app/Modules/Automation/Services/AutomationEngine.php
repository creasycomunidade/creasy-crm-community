<?php

namespace App\Modules\Automation\Services;

use App\Events\ConversationAssigned;
use App\Events\MessageSent;
use App\Mail\AutomationEmail;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Services\ChatbotRunner;
use App\Modules\AI\Services\LlmGateway;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Models\AutomationRunLog;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Broadcasting\Services\Sms\SmsDriverManager;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentService;
use App\Modules\Ecommerce\Models\EcommerceProduct;
use App\Modules\Integrations\Services\Clients\GoogleClient;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use App\Support\PublicHttpUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

/**
 * Event-driven automation execution engine.
 *
 * Executes automation nodes sequentially. Each node has a `type` and `data`.
 * Edges define the flow between nodes. Entry node is the first node after the trigger.
 *
 * Supported node types:
 *   - trigger            (entry point, skipped at runtime)
 *   - send_whatsapp      (send WhatsApp template/text)
 *   - send_sms           (send SMS via SmsDriverManager)
 *   - send_email         (send email via Laravel Mail)
 *   - wait               (delay in minutes/hours/days)
 *   - condition          (if/else branch on contact attribute or event)
 *   - add_tag / remove_tag
 *   - update_contact     (set custom field value)
 *   - add_to_campaign    (enqueue contact to broadcast campaign)
 *   - ai_reply           (generate AI response and send)
 *   - webhook            (POST JSON payload to URL)
 */
class AutomationEngine
{
    public function __construct(
        private readonly ChannelManager $channelManager,
        private readonly ChatbotRunner $chatbotRunner,
        private readonly LlmGateway $llmGateway,
    ) {}

    public function triggerForContact(Automation $automation, int $contactId, array $context = []): void
    {
        if (! $automation->isActive()) {
            return;
        }

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contactId,
            'status' => 'pending',
            'context' => $context,
            'started_at' => now(),
        ]);

        dispatch(new ExecuteAutomationRunJob($run->id))->onQueue('automation');
    }

    /**
     * Resume runs that are parked on an "Ask question" node, waiting for this
     * contact's next inbound message. The reply body is stored in the configured
     * context variable and the run continues from the node after the question.
     */
    public function resumeAwaitingReplies(int $workspaceId, int $contactId, string $messageBody, ?int $conversationId = null): bool
    {
        $handled = false;
        $runs = AutomationRun::where('contact_id', $contactId)
            ->where('status', 'waiting')
            ->whereHas('automation', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->get();

        foreach ($runs as $run) {
            $context = $run->context ?? [];
            if (empty($context['_awaiting_reply'])) {
                continue; // a plain wait/delay — not waiting for a reply
            }

            if (!empty($context['_reply_conversation']) && $conversationId !== (int)$context['_reply_conversation']) continue;
            $handled = true;
            $kind = $context['_reply_type'] ?? 'text';
            $valid = match ($kind) {
                'time' => (bool)preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',trim($messageBody)),
                'rating' => in_array(trim($messageBody),['1','2','3','4','5'],true),
                'email' => filter_var($messageBody, FILTER_VALIDATE_EMAIL) !== false,
                'number' => is_numeric(str_replace(',', '.', $messageBody)),
                'phone' => (bool) preg_match('/^\+?[1-9][0-9 ()-]{6,20}$/', $messageBody),
                'url' => filter_var($messageBody, FILTER_VALIDATE_URL) !== false && in_array(parse_url($messageBody, PHP_URL_SCHEME), ['https','http'], true),
                'date' => (bool) $this->parseDateTime($messageBody),
                'choice' => in_array(trim($messageBody), $context['_reply_choices'] ?? [], true),
                default => trim($messageBody) !== '',
            };
            if (!$valid) {
                AutomationRunLog::create(['run_id'=>$run->id,'node_id'=>$run->current_node_id,'node_type'=>'input_'.$kind,'result'=>'skipped','message'=>'Resposta fora do formato esperado. Aguardando nova resposta.']);
                continue;
            }
            $var = $context['_reply_var'] ?? 'answer';
            $context[$var] = $messageBody;
            unset($context['_awaiting_reply'], $context['_reply_var'], $context['_reply_type'], $context['_reply_choices'], $context['_reply_conversation']);
            $run->update(['context' => $context]);

            if (!$run->resume_node_id) { $run->update(['status'=>'completed','completed_at'=>now()]); continue; }
            dispatch(new ExecuteAutomationRunJob($run->id))->onQueue('automation');
        }
        return $handled;
    }

    public function executeRun(AutomationRun $run): void
    {
        $run->update(['status' => 'running']);
        $automation = $run->automation;

        $nodes = collect($automation->nodes ?? []);
        $edges = collect($automation->edges ?? []);
        $context = $run->context ?? [];

        // If resuming after a wait, start from the node after the wait
        if ($run->resume_node_id) {
            $currentId = $run->resume_node_id;
            $run->update(['resume_node_id' => null]);
        } else {
            // Find trigger node and start from the first node after it
            $triggerNode = $nodes->first(fn ($n) => in_array($n['type'] ?? '', ['trigger', 'triggerNode'], true) || isset($n['data']['triggerType']));
            if (! $triggerNode) {
                $run->update(['status' => 'failed', 'error' => 'No trigger node.', 'completed_at' => now()]);

                return;
            }
            $firstEdge = $edges->first(fn ($e) => $e['source'] === ($triggerNode['id'] ?? null));
            $currentId = $firstEdge['target'] ?? null;
        }

        $visited = [];
        $maxSteps = 100;

        while ($currentId && $maxSteps-- > 0) {
            if (in_array($currentId, $visited)) {
                break; // cycle guard
            }
            $visited[] = $currentId;

            $node = $nodes->first(fn ($n) => $n['id'] === $currentId);
            if (! $node) {
                break;
            }

            // Update current_node_id before executing so child methods (e.g. executeWait)
            // can read the correct node ID when looking up outgoing edges.
            $run->update(['current_node_id' => $currentId]);

            $result = $this->executeNode($node, $run, $context);
            $context = array_merge($context, $result['context_update'] ?? []);
            $run->update(['context' => $context]);

            AutomationRunLog::create([
                'run_id' => $run->id,
                'node_id' => $currentId,
                'node_type' => $node['type'] ?? 'unknown',
                'result' => match ($result['status'] ?? 'ok') {
                    'error' => 'error',
                    'skipped' => 'skipped',
                    default => 'ok',
                },
                'message' => $result['message'] ?? null,
                'output' => $result['output'] ?? null,
            ]);

            if (($result['status'] ?? 'ok') === 'error') {
                $run->update(['status' => 'failed', 'error' => $result['message'], 'completed_at' => now()]);

                return;
            }

            if (!empty($result['stop'])) break;

            // Wait node suspends the run; wakeup job will continue from the stored next node
            if (($result['status'] ?? '') === 'waiting') {
                return;
            }

            // Condition branching
            $nextEdgeLabel = $result['branch'] ?? null;

            // Find next edge
            $nextEdge = $edges->first(fn ($e) => $e['source'] === $currentId &&
                (! isset($nextEdgeLabel) || ($e['sourceHandle'] ?? null) === $nextEdgeLabel)
            );

            $currentId = $nextEdge['target'] ?? null;
            $nextEdgeLabel = null;
        }

        $run->update(['status' => 'completed', 'completed_at' => now()]);
        $automation->increment('run_count');
    }

    /**
     * Simulate a workflow run for the builder's "Test" button. Walks the same graph the
     * engine would, evaluating conditions for real (read-only) but only *previewing*
     * side-effecting nodes — no messages are sent, no records written, no external APIs
     * called and no AI tokens spent. Returns a step-by-step trace for the UI.
     *
     * @param  array<int, array<string, mixed>>  $nodes  builder node objects ({id, type, data})
     * @param  array<int, array<string, mixed>>  $edges  builder edge objects ({source, target, sourceHandle})
     * @return array{ok: bool, error?: string, steps: array<int, array<string, mixed>>, context?: array<string, mixed>, contact?: array<string, mixed>}
     */
    public function testRun(Automation $automation, array $nodes, array $edges, array $context = []): array
    {
        $nodesC = collect($nodes);
        $edgesC = collect($edges);

        $contact = $this->sampleContact((int) $automation->workspace_id);
        $context = array_merge($this->defaultTestContext(), $context);
        if (empty($context['trigger_account']) && ! empty($automation->trigger_config['account_key'])) {
            $context['trigger_account'] = $automation->trigger_config['account_key'];
        }

        $isTrigger = fn ($n) => in_array($n['type'] ?? '', ['trigger', 'triggerNode'], true) || isset($n['data']['triggerType']);
        $trigger = $nodesC->first($isTrigger);

        if (! $trigger) {
            return ['ok' => false, 'error' => 'Add a trigger to start the automation.', 'steps' => []];
        }
        $triggerType = $automation->trigger_type ?: ($trigger['data']['triggerType'] ?? null);
        if (! $triggerType) {
            return ['ok' => false, 'error' => 'Pick a trigger type before testing.', 'steps' => []];
        }

        $firstEdge = $edgesC->first(fn ($e) => ($e['source'] ?? null) === ($trigger['id'] ?? null));
        $currentId = $firstEdge['target'] ?? null;
        if (! $currentId) {
            return ['ok' => false, 'error' => 'Connect the trigger to at least one step.', 'steps' => []];
        }

        $steps = [];
        $visited = [];
        $maxSteps = 60;

        while ($currentId && $maxSteps-- > 0) {
            if (in_array($currentId, $visited, true)) {
                $steps[] = ['node_id' => $currentId, 'node_type' => 'loop', 'label' => null, 'result' => 'skipped', 'message' => 'Loop detected — stopping here.', 'branch' => null];
                break;
            }
            $visited[] = $currentId;

            $node = $nodesC->first(fn ($n) => ($n['id'] ?? null) === $currentId);
            if (! $node) {
                break;
            }
            $type = $node['data']['nodeType'] ?? $node['type'] ?? 'unknown';
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];

            $branch = null;
            if ($type === 'condition') {
                $passed = $this->evaluateCondition($data, $contact, $context);
                $branch = $passed ? 'true' : 'false';
                $result = $this->conditionResult($passed, $data['field'] ?? null, $data['operator'] ?? 'equals', $data['value'] ?? null);
            } else {
                $result = $this->previewNode($type, $data, $contact, $context);
                $branch = $result['branch'] ?? null;
            }

            $context = array_merge($context, $result['context_update'] ?? []);

            $steps[] = [
                'node_id' => $currentId,
                'node_type' => $type,
                'label' => $data['label'] ?? null,
                'result' => $result['status'] ?? 'ok',
                'message' => $result['message'] ?? '',
                'output' => $result['output'] ?? null,
                'branch' => $branch,
            ];

            if (($result['status'] ?? 'ok') === 'error' || !empty($result['stop'])) {
                break;
            }

            $nextEdge = $edgesC->first(fn ($e) => ($e['source'] ?? null) === $currentId
                && ($branch === null || ($e['sourceHandle'] ?? null) === $branch));
            $currentId = $nextEdge['target'] ?? null;
        }

        return [
            'ok' => true,
            'steps' => $steps,
            'context' => $context,
            'contact' => [
                'name' => $contact->full_name,
                'email' => $contact->email,
                'phone' => $contact->phone_e164,
            ],
        ];
    }

    /** A throw-away, unsaved contact used for test simulations so we never touch real data. */
    private function sampleContact(int $workspaceId): Contact
    {
        $c = new Contact;
        $c->workspace_id = $workspaceId;
        $c->first_name = 'Test';
        $c->last_name = 'Contact';
        $c->email = 'test.contact@example.com';
        $c->phone_e164 = '+15555550123';
        $c->language = 'en';
        $c->country = 'US';

        return $c;
    }

    /** Seed run-context values so {{context.*}} tokens render during a test. */
    private function defaultTestContext(): array
    {
        return [
            'message_body' => 'Hi',
            'message_channel' => 'whatsapp',
            'order_number' => '1042',
            'order_total' => '49.00',
            'order_currency' => 'USD',
            'tracking_url' => 'https://example.com/track/1042',
            'store_name' => 'Demo Store',
            'cart_total' => '49.00',
            'recovery_url' => 'https://example.com/cart/abc',
        ];
    }

    /**
     * Describe what a node *would* do — without performing any side effect. Used by testRun()
     * only; mirrors the validation each real executor performs so the trace flags mis-config.
     */
    private function previewNode(string $type, array $data, Contact $contact, array $context): array
    {
        $render = fn ($v) => $this->renderTokens((string) ($v ?? ''), $contact, $context);
        $ok = fn (string $msg, array $extra = []) => array_merge(['status' => 'ok', 'message' => $msg], $extra);
        $err = fn (string $msg) => ['status' => 'error', 'message' => $msg];
        $skip = fn (string $msg) => ['status' => 'skipped', 'message' => $msg];

        if (in_array($type, ['quick_replies', 'cta_button', 'poll'], true)) {
            if ($type === 'quick_replies' && (trim($render($data['body'] ?? '')) === '' || empty($this->toList($data['buttons'] ?? [])))) {
                return $err('Informe a mensagem e pelo menos um botão.');
            }
            $target = $this->resolveChannelTarget((int) $contact->workspace_id, $contact, 'whatsapp',
                ! empty($data['channel_account_id']) ? (int) $data['channel_account_id'] : null);
            if ($target['error']) return $err($target['error']);
            $interactiveType = match ($type) {
                'cta_button' => 'cta_url',
                'poll' => count($this->toList($data['options'] ?? [])) > 3 ? 'list' : 'button',
                default => 'button',
            };
            if (! AutomationChannelCapabilities::supports($target['account'], 'interactive', ['interactive' => ['type' => $interactiveType]])) {
                return $err('A conexão de WhatsApp selecionada não suporta este tipo de botão ou enquete. Use uma conta compatível ou uma mensagem de texto.');
            }
        }
        if (in_array($type, ['reply_comment', 'private_reply'], true)
            && ! empty($context['trigger_account']) && ! str_starts_with($context['trigger_account'], 'zernio:')) {
            return $err('Este bloco de resposta a comentários requer uma conta Zernio. Contas conectadas diretamente à Meta ainda não são suportadas por este bloco.');
        }

        if (!empty($data['channel_account_id']) && in_array($type,['send_sequence','send_media','send_whatsapp','send_instagram','send_messenger','send_telegram'],true)) {
            $account=ChannelAccount::where('workspace_id',$contact->workspace_id)->where('status','active')->find($data['channel_account_id']);
            if (!$account) return $err('A conta de envio está indisponível neste espaço.');
            $formats=$type==='send_sequence' ? array_map(fn($s)=>$s['kind']==='media'?$s['media_type']:'text',$this->parseSteps($data['steps']??[])) : [$type==='send_media'?($data['media_type']??'image'):'text'];
            foreach ($formats as $format) if (!in_array($format,AutomationChannelCapabilities::types($account),true)) return $err('Formato '.$format.' não suportado pela conta '.$account->display_name.'.');
        }
        if ($type === 'list_message' && !empty($data['channel_account_id'])) {
            $account = ChannelAccount::where('workspace_id', $contact->workspace_id)->where('channel', 'whatsapp')->where('status', 'active')->find($data['channel_account_id']);
            if (!$account || !AutomationChannelCapabilities::supports($account, 'interactive', ['interactive' => ['type' => 'list']])) {
                return $err('Escolha uma conta de WhatsApp compatível com listas.');
            }
        }
        if (isset(ExtendedAutomationNodes::catalog()[$type])) {
            try { ExtendedAutomationNodes::validate($type, $data); }
            catch (\Throwable $e) { return $err('Preencha os campos obrigatórios do nó. '.$e->getMessage()); }
            $extra = match ($type) {
                'set_variable'=>['context_update'=>[$data['variable']=>$render($data['value']??'')]],
                'random_split'=>['branch'=>(int)($data['percentage']??50)>=50?'true':'false'],
                'stop_flow'=>['stop'=>true],
                default=>[],
            };
            return $ok('Simulação: '.ExtendedAutomationNodes::catalog()[$type]['label'].'. Nenhuma ação externa executada.', $extra);
        }
        return match ($type) {
            'send_whatsapp' => $ok('Would send WhatsApp: "'.$this->snippet($render($data['body'] ?? '')).'"'),
            'send_sms' => $ok('Would send SMS: "'.$this->snippet($render($data['body'] ?? '')).'"'),
            'send_email' => ($data['subject'] ?? '') === ''
                ? $err('Email subject is required.')
                : $ok('Would email "'.$this->snippet($render($data['subject'])).'" to '.$contact->email),
            'send_template' => ($data['template_name'] ?? ($data['template_ref'] ?? '')) === ''
                ? $err('No template selected.')
                : $ok('Would send template "'.($data['template_name'] ?? $data['template_ref']).'" ('.($data['language'] ?? 'en').').'),
            'send_media' => ($data['link'] ?? '') === ''
                ? $err('Media link is required.')
                : $ok('Would send '.($data['media_type'] ?? 'image').': '.$this->snippet($render($data['link']), 50)),
            'send_sequence' => $ok('Would send '.count($this->parseSteps($data['steps'] ?? [])).' sequence step(s).'),
            'quick_replies' => $ok('Would send buttons: '.(implode(' · ', array_slice($this->toList($data['buttons'] ?? []), 0, 3)) ?: '—')),
            'list_message' => $ok('Would send a list with '.count($this->parseRows($data['rows'] ?? [])).' item(s).'),
            'ask_question' => $ok('Would ask: "'.$this->snippet($render($data['question'] ?? '')).'" → saved to {{context.'.(($data['variable'] ?? '') ?: 'answer').'}}',
                ['context_update' => [(($data['variable'] ?? '') ?: 'answer') => '[sample reply]']]),
            'wait' => $ok('Would wait '.((int) ($data['amount'] ?? 1)).' '.($data['unit'] ?? 'minutes').' (skipped in test).'),
            'webhook' => ($data['url'] ?? '') === ''
                ? $err('Webhook URL missing.')
                : $ok('Would call '.strtoupper($data['method'] ?? 'POST').' '.$this->snippet($render($data['url']), 50), ['context_update' => ['webhook_status' => 200]]),
            'run_subflow' => $ok('Would run sub-flow '.($data['subflow_name'] ?? ($data['automation_uuid'] ?? '?')).'.'),
            'ai_reply' => $ok('Would generate an AI reply'.(! empty($data['chatbot_id']) ? ' via chatbot #'.$data['chatbot_id'] : '').' and send it.', ['context_update' => ['last_ai_reply' => '[AI generated reply]']]),
            'add_tag' => ($data['tag'] ?? '') === '' ? $skip('No tag name.') : $ok('Would add tag "'.$data['tag'].'".'),
            'remove_tag' => ($data['tag'] ?? '') === '' ? $skip('No tag name.') : $ok('Would remove tag "'.$data['tag'].'".'),
            'update_contact' => ($data['field'] ?? '') === '' ? $skip('No field selected.') : $ok('Would set contact.'.$data['field'].' = "'.$this->snippet($render($data['value'] ?? '')).'".'),
            'assign_agent' => $ok(! empty($data['agent_name']) ? 'Would assign to '.$data['agent_name'].'.' : 'Would hand off to a human agent.'),
            'add_to_campaign' => ($data['campaign_id'] ?? '') === '' ? $skip('No campaign selected.') : $ok('Would add contact to campaign #'.$data['campaign_id'].'.'),
            'cta_button' => $ok('Would send CTA "'.($data['display_text'] ?? 'Open').'" → '.$this->snippet($render($data['url'] ?? ''), 40)),
            'send_location' => $ok('Would send location '.($data['latitude'] ?? '?').', '.($data['longitude'] ?? '?').'.'),
            'send_poll' => $ok('Would send a poll: "'.$this->snippet($render($data['question'] ?? '')).'"'),
            'run_chatbot' => empty($data['chatbot_id']) ? $err('No chatbot selected.') : $ok('Would run chatbot #'.$data['chatbot_id'].' and send the reply.', ['context_update' => ['last_ai_reply' => '[chatbot reply]']]),
            'book_appointment' => $ok('Would book "'.$this->snippet($render($data['summary'] ?? 'Appointment'), 30).'" at '.($data['start'] ?? '?').'.', ['context_update' => ['appointment_link' => 'https://calendar.example.com/evt']]),
            'google_meet' => $ok('Would create a Google Meet for "'.$this->snippet($render($data['summary'] ?? 'Meeting'), 30).'".', ['context_update' => ['meet_url' => 'https://meet.google.com/abc-defg-hij']]),
            'whatsapp_form' => ($data['flow_id'] ?? '') === '' ? $err('Flow ID is required.') : $ok('Would send WhatsApp flow #'.$data['flow_id'].'.'),
            'whatsapp_catalog' => $ok('Would send the WhatsApp catalog.'),
            'woocommerce_product', 'shopify_product' => ($data['product_id'] ?? '') === '' ? $err('No product selected.') : $ok('Would send product #'.$data['product_id'].'.'),
            'google_sheets' => ($data['spreadsheet_id'] ?? '') === '' ? $err('Spreadsheet ID is required.') : $ok('Would '.($data['mode'] ?? 'append').' Google Sheet range '.($data['range'] ?? '').'.'),
            'google_docs' => ($data['template_doc_id'] ?? '') === '' ? $err('Template document ID is required.') : $ok('Would generate a Google Doc from template.', ['context_update' => ['doc_url' => 'https://docs.google.com/document/d/sample']]),
            'google_forms' => ($data['form_id'] ?? '') === ''
                ? $err('Form ID is required.')
                : (($data['mode'] ?? 'send_link') === 'read_response'
                    ? $ok('Would read the latest Google Form response.', ['context_update' => [(($data['result_var'] ?? '') ?: 'form').'_json' => '{}']])
                    : $ok('Would share the Google Form link.', ['context_update' => ['form_url' => 'https://docs.google.com/forms/d/sample/viewform']])),
            default => $skip('Unknown node type: '.$type),
        };
    }

    private function snippet(string $s, int $n = 40): string
    {
        $s = trim($s);

        return mb_strlen($s) > $n ? mb_substr($s, 0, $n).'…' : $s;
    }

    private function executeNode(array $node, AutomationRun $run, array $context): array
    {
        $type = $node['data']['nodeType'] ?? $node['type'] ?? 'unknown';
        if (in_array($type, ['send_sms', 'add_to_campaign', 'woocommerce_product', 'shopify_product', 'save_memory', 'lookup_lead', 'social_draft', 'publish_social_post', 'ads_summary', 'lookup_order', 'lookup_service_order', 'social_message', 'reply_comment', 'private_reply', 'reply_review'], true)) {
            return ['status' => 'error', 'message' => 'Este nó depende de um módulo não incluído na comunidade.'];
        }
        $data = $node['data'] ?? [];

        try {
            if (str_starts_with($type, 'input_') && isset(ExtendedAutomationNodes::catalog()[$type])) {
                ExtendedAutomationNodes::validate($type, $data);
                $data['input_type'] = substr($type, 6);
                return $this->executeAskQuestion($data, $run, $context);
            }
            if (in_array($type, ['send_instagram','send_messenger','send_telegram'], true)) {
                ExtendedAutomationNodes::validate($type, $data);
                return $this->executeSendWhatsapp(array_merge($data, ['channel'=>substr($type,5)]), $run, $context);
            }
            if (isset(ExtendedAutomationNodes::catalog()[$type])) return app(ExtendedAutomationNodes::class)->execute($type, $data, $run, $context);
            return match ($type) {
                'wait' => $this->executeWait($data, $run),
                'add_tag' => $this->executeTagAction($data, $run, 'add'),
                'remove_tag' => $this->executeTagAction($data, $run, 'remove'),
                'update_contact' => $this->executeUpdateContact($data, $run, $context),
                'webhook' => $this->executeWebhook($data, $run, $context),
                'condition' => $this->executeCondition($data, $run, $context),
                'send_whatsapp' => $this->executeSendWhatsapp($data, $run, $context),
                'send_sms' => $this->executeSendSms($data, $run, $context),
                'send_email' => $this->executeSendEmail($data, $run, $context),
                'ai_reply' => $this->executeAiReply($data, $run, $context),
                'add_to_campaign' => $this->executeAddToCampaign($data, $run),
                // ── SEND ──────────────────────────────────────────────────────
                'send_template' => $this->executeSendTemplate($data, $run, $context),
                'send_media' => $this->executeSendMedia($data, $run, $context),
                'send_sequence' => $this->executeSendSequence($data, $run, $context),
                'quick_replies' => $this->executeQuickReplies($data, $run, $context),
                'list_message' => $this->executeListMessage($data, $run, $context),
                // ── LISTEN ────────────────────────────────────────────────────
                'ask_question' => $this->executeAskQuestion($data, $run, $context),
                // ── LOGIC ─────────────────────────────────────────────────────
                'run_subflow' => $this->executeRunSubflow($data, $run, $context),
                // ── CONTACT ───────────────────────────────────────────────────
                'assign_agent' => $this->executeAssignAgent($data, $run),
                // ── ENGAGE ────────────────────────────────────────────────────
                'cta_button' => $this->executeCtaButton($data, $run, $context),
                'send_location' => $this->executeSendLocation($data, $run, $context),
                'send_poll' => $this->executeSendPoll($data, $run, $context),
                'run_chatbot' => $this->executeRunChatbot($data, $run, $context),
                'book_appointment' => $this->executeBookAppointment($data, $run, $context),
                'google_meet' => $this->executeGoogleMeet($data, $run, $context),
                'whatsapp_form' => $this->executeWhatsappForm($data, $run, $context),
                // ── COMMERCE ──────────────────────────────────────────────────
                'whatsapp_catalog' => $this->executeWhatsappCatalog($data, $run, $context),
                'woocommerce_product' => $this->executeSendProduct($data, $run, $context, 'woocommerce'),
                'shopify_product' => $this->executeSendProduct($data, $run, $context, 'shopify'),
                // ── INTEGRATIONS ──────────────────────────────────────────────
                'google_sheets' => $this->executeGoogleSheets($data, $run, $context),
                'google_docs' => $this->executeGoogleDocs($data, $run, $context),
                'google_forms' => $this->executeGoogleForms($data, $run, $context),
                default => ['status' => 'error', 'message' => "Nó não suportado: {$type}"],
            };
        } catch (\Throwable $e) {
            Log::error("AutomationEngine node error [{$type}]: ".$e->getMessage());

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ─── Channel send helpers ────────────────────────────────────────────────

    private function executeSendWhatsapp(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }

        // Back-compat: a template_ref still sends an approved template (WhatsApp only).
        if (! empty($data['template_ref'])) {
            return $this->sendWhatsappPayload($run, 'template', null, ['template' => [
                'name' => $data['template_ref'],
                'language' => $data['language'] ?? 'en',
                'components' => [],
            ]]);
        }

        $body = $this->renderTokens($data['body'] ?? '', $contact, $context);

        return $this->dispatchMessage($run, $this->pickChannel($data, $context), 'text', $body, null, 'automation', ! empty($data['channel_account_id']) ? (int) $data['channel_account_id'] : null);
    }

    private function executeSendSms(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }

        $body = $this->renderTokens($data['body'] ?? '', $contact, $context);

        return $this->dispatchMessage($run, 'sms', 'text', $body, null);
    }

    private function executeSendEmail(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact || ! $contact->email) {
            return ['status' => 'skipped', 'message' => 'Contact has no email address.'];
        }

        $subject = $this->renderTokens($data['subject'] ?? 'Mensagem para você', $contact, $context);
        $body = $this->renderTokens($data['body'] ?? '', $contact, $context);

        Mail::to($contact->email)->queue(
            new AutomationEmail($subject, $body)
        );

        return ['status' => 'ok', 'message' => "Email queued to {$contact->email}."];
    }

    /**
     * AI assistant node. Two modes:
     *   - chatbot_id set  → run the RAG chatbot (knowledge-base aware).
     *   - prompt only     → free-form generation via the workspace LLM (ChatGPT / Gemini).
     */
    private function executeAiReply(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }

        $workspaceId = $run->automation->workspace_id;
        $userMessage = (string) ($context['message_body'] ?? '');

        if (! empty($data['chatbot_id'])) {
            $bot = AiChatbot::where('id', $data['chatbot_id'])
                ->where('workspace_id', $workspaceId)
                ->first();

            if (! $bot || ! $bot->enabled) {
                return ['status' => 'error', 'message' => 'Chatbot not found or disabled.'];
            }

            $prompt = $userMessage !== ''
                ? $userMessage
                : $this->renderTokens($data['prompt'] ?? '', $contact, $context);

            $result = $this->chatbotRunner->runForApi($bot, $prompt, $workspaceId, $context['history'] ?? []);
            $reply = $result['reply'] ?? null;
            $tokens = $result['tokens_used'] ?? 0;
            $productIds = $result['product_ids'] ?? [];
        } else {
            $system = $this->renderTokens($data['prompt'] ?? 'You are a helpful assistant.', $contact, $context);
            $messages = [['role' => 'system', 'content' => $system]];
            $messages[] = [
                'role' => 'user',
                'content' => $userMessage !== '' ? $userMessage : 'Write a helpful, friendly message to the contact.',
            ];

            try {
                $response = $this->llmGateway->chat($workspaceId, $messages, ['max_tokens' => 512]);
                $reply = $response->content;
                $tokens = $response->promptTokens + $response->completionTokens;
                $productIds = [];
            } catch (\Throwable $e) {
                return ['status' => 'error', 'message' => 'AI generation failed: '.$e->getMessage()];
            }
        }

        if (! $reply) {
            return ['status' => 'skipped', 'message' => 'AI returned no reply.'];
        }

        $channel = $this->pickChannel($data, $context);
        $send = $this->sendTextViaChannel($run, $channel, $reply, 'bot');
        if (($send['status'] ?? 'error') === 'ok') {
            foreach ($productIds as $productId) {
                $this->sendCatalogProduct($run, $channel, (int) $productId);
            }
        }

        return [
            'status' => $send['status'] ?? 'ok',
            'message' => ($send['status'] ?? 'ok') === 'ok' ? 'AI reply sent.' : $send['message'],
            'output' => ['reply' => $reply, 'tokens_used' => $tokens],
            'context_update' => ['last_ai_reply' => $reply],
        ];
    }

    private function executeAddToCampaign(array $data, AutomationRun $run): array
    {
        $campaignId = $data['campaign_id'] ?? null;
        if (! $campaignId || ! $run->contact_id) {
            return ['status' => 'skipped', 'message' => 'No campaign_id or contact.'];
        }

        $campaign = Campaign::find($campaignId);
        if (! $campaign) {
            return ['status' => 'error', 'message' => "Campaign {$campaignId} not found."];
        }

        // Scope check
        if ((int) $campaign->workspace_id !== (int) $run->automation->workspace_id) {
            return ['status' => 'error', 'message' => 'Campaign belongs to a different workspace.'];
        }

        // Skip if already a recipient
        $exists = CampaignRecipient::where('campaign_id', $campaignId)
            ->where('contact_id', $run->contact_id)
            ->exists();

        if ($exists) {
            return ['status' => 'skipped', 'message' => 'Contact already in campaign.'];
        }

        CampaignRecipient::create([
            'campaign_id' => $campaignId,
            'contact_id' => $run->contact_id,
            'status' => 'queued',
        ]);

        return ['status' => 'ok', 'message' => "Contact added to campaign {$campaignId}."];
    }

    // ─── Token replacement ───────────────────────────────────────────────────

    private function renderTokens(string $template, ?Contact $contact, array $context): string
    {
        return AutomationVariables::render($template, $contact, $context);
    }

    // ─── Existing helpers ────────────────────────────────────────────────────

    private function resolveOrCreateConversation(Contact $contact, ChannelAccount $account, string $channel = 'whatsapp'): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'workspace_id' => $account->workspace_id,
                'channel_account_id' => $account->id,
                'contact_id' => $contact->id,
            ],
            [
                'status' => 'open',
                'unread_count' => 0,
                // WhatsApp/SMS address the contact by phone; Messenger/Instagram require an
                // existing thread (PSID/IGSID) so they never reach this create path.
                'external_thread_id' => in_array($channel, ['whatsapp', 'sms'], true) ? $contact->phone_e164 : null,
            ]
        );
    }

    private function executeWait(array $data, AutomationRun $run): array
    {
        $amount = (int) ($data['amount'] ?? 1);
        $unit = $data['unit'] ?? 'minutes';
        $delay = match ($unit) {
            'hours' => $amount * 60,
            'days' => $amount * 1440,
            default => $amount,
        };

        // Find the next node after this wait node so the wakeup job resumes there
        $automation = $run->automation;
        $edges = collect($automation->edges ?? []);
        $nextEdge = $edges->first(fn ($e) => $e['source'] === $run->current_node_id);
        $nextNodeId = $nextEdge['target'] ?? null;

        // Persist the resume cursor and mark the run as waiting
        $run->update([
            'status' => 'waiting',
            'resume_node_id' => $nextNodeId,
        ]);

        // Schedule the wakeup
        dispatch(new ExecuteAutomationRunJob($run->id))
            ->delay(now()->addMinutes($delay))
            ->onQueue('automation');

        return ['status' => 'waiting', 'message' => "Waiting {$amount} {$unit}."];
    }

    private function executeTagAction(array $data, AutomationRun $run, string $action): array
    {
        $tagName = $data['tag'] ?? null;
        if (! $tagName || ! $run->contact_id) {
            return ['status' => 'skipped', 'message' => 'No tag or contact.'];
        }
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $tag = ContactTag::firstOrCreate(
            ['workspace_id' => $contact->workspace_id, 'name' => $tagName],
        );
        if ($action === 'add') {
            $contact->tags()->syncWithoutDetaching([$tag->id]);
        } else {
            $contact->tags()->detach($tag->id);
        }

        return ['status' => 'ok', 'message' => ucfirst($action)." tag '{$tagName}'."];
    }

    private function executeUpdateContact(array $data, AutomationRun $run, array $context): array
    {
        if (! $run->contact_id) {
            return ['status' => 'skipped', 'message' => 'No contact.'];
        }
        $field = $data['field'] ?? null;
        if (! $field) {
            return ['status' => 'skipped', 'message' => 'No field specified.'];
        }
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $value = $this->renderTokens((string) ($data['value'] ?? ''), $contact, $context);

        // Map the builder's friendly field names onto real Contact columns; anything
        // unrecognised (incl. "notes") is stored under custom_fields.
        switch ($field) {
            case 'name':
                $parts = preg_split('/\s+/', trim($value), 2);
                $contact->first_name = $parts[0] ?? '';
                $contact->last_name = $parts[1] ?? '';
                break;
            case 'first_name':
                $contact->first_name = $value;
                break;
            case 'last_name':
                $contact->last_name = $value;
                break;
            case 'phone':
            case 'phone_e164':
                $contact->phone_e164 = $value;
                break;
            case 'email':
                $contact->email = $value;
                break;
            case 'language':
                $contact->language = $value;
                break;
            case 'country':
                $contact->country = $value;
                break;
            default:
                $key = str_starts_with($field, 'custom.') ? substr($field, 7) : $field;
                $custom = $contact->custom_fields ?? [];
                $custom[$key] = $value;
                $contact->custom_fields = $custom;
                break;
        }
        $contact->save();

        return ['status' => 'ok', 'message' => "Updated contact.{$field}."];
    }

    private function executeWebhook(array $data, AutomationRun $run, array $context): array
    {
        $contact = $run->contact_id ? Contact::find($run->contact_id) : null;
        $url = (string) ($data['url'] ?? '');
        if ($url === '') {
            return ['status' => 'error', 'message' => 'Webhook URL missing.'];
        }
        if ($contact) {
            $url = $this->renderTokens($url, $contact, $context);
        }

        try {
            $requestOptions = PublicHttpUrl::requestOptions($url);
        } catch (\InvalidArgumentException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $method = strtolower($data['method'] ?? 'POST');
        if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
            $method = 'post';
        }

        // The builder stores headers/payload as JSON strings — decode + token-render them.
        $headers = $this->decodeJsonField($data['headers'] ?? null, $contact, $context);
        $headers = array_filter(
            $headers,
            fn (mixed $value, string|int $name): bool => ! in_array(
                strtolower((string) $name),
                ['host', 'content-length', 'transfer-encoding', 'connection'],
                true,
            ),
            ARRAY_FILTER_USE_BOTH,
        );
        $payload = $this->decodeJsonField($data['payload'] ?? null, $contact, $context);

        $request = Http::timeout(10)->withOptions($requestOptions);
        if (! empty($headers)) {
            $request = $request->withHeaders($headers);
        }

        $response = $method === 'get'
            ? $request->get($url, $payload)
            : $request->{$method}($url, array_merge($payload, ['context' => $context]));

        return [
            'status' => 'ok',
            'message' => "Webhook {$method} {$url} → {$response->status()}",
            'output' => ['status' => $response->status()],
            'context_update' => ['webhook_status' => $response->status(), 'webhook_body' => strlen($response->body()) <= 65536 ? ($response->json() ?? $response->body()) : '[Resposta maior que 64 KB]'],
        ];
    }

    /** Decode a JSON-string (or already-array) config field into an array, token-rendered. */
    private function decodeJsonField(mixed $v, ?Contact $contact, array $context): array
    {
        if (is_array($v)) {
            return $v;
        }
        if (! is_string($v) || trim($v) === '') {
            return [];
        }
        $rendered = $contact ? $this->renderTokens($v, $contact, $context) : $v;
        $decoded = json_decode($rendered, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function executeCondition(array $data, AutomationRun $run, array $context): array
    {
        $contact = $run->contact_id ? Contact::find($run->contact_id) : null;
        $passed = $this->evaluateCondition($data, $contact, $context);

        return $this->conditionResult($passed, $data['field'] ?? null, $data['operator'] ?? 'equals', $data['value'] ?? null);
    }

    /**
     * Pure boolean evaluation of a condition node against a contact + run context.
     * Read-only: shared by live execution and the builder's test simulation.
     */
    public function evaluateCondition(array $data, ?Contact $contact, array $context): bool
    {
        $field = $data['field'] ?? null;
        $operator = $data['operator'] ?? 'equals';
        $value = $data['value'] ?? null;

        // Tag membership is a boolean check, not a value comparison.
        if ($field === 'contact.tag') {
            $has = ($contact && $contact->exists) ? $contact->tags()->where('name', $value)->exists() : false;

            return in_array($operator, ['not_equals', 'not_contains', 'not_exists'], true) ? ! $has : $has;
        }

        // Resolve the actual value from the contact, the inbound message, or the run context.
        $actual = match (true) {
            $field === 'contact.name' => optional($contact)->full_name,
            (bool) $field && str_starts_with((string) $field, 'contact.') => ($field === 'contact.phone' ? $contact?->phone_e164 : data_get($contact, substr($field, 8))),
            $field === 'message.body' => $context['message_body'] ?? null,
            (bool) $field && str_starts_with((string) $field, 'context.') => data_get($context, substr($field, 8)),
            default => $context[$field] ?? null,
        };

        return match ($operator) {
            'equals' => (string) $actual === (string) $value,
            'not_equals' => (string) $actual !== (string) $value,
            'contains' => $value !== null && str_contains((string) $actual, (string) $value),
            'not_contains' => $value === null || ! str_contains((string) $actual, (string) $value),
            'exists' => $actual !== null && $actual !== '' && $actual !== false,
            'not_exists' => $actual === null || $actual === '' || $actual === false,
            'gt' => (float) $actual > (float) $value,
            'lt' => (float) $actual < (float) $value,
            default => false,
        };
    }

    private function conditionResult(bool $passed, ?string $field, string $operator, mixed $value): array
    {
        return [
            'status' => 'ok',
            'branch' => $passed ? 'true' : 'false',
            'message' => "Condition: {$field} {$operator} {$value} → ".($passed ? 'true' : 'false'),
        ];
    }

    // ─── SEND nodes ───────────────────────────────────────────────────────────

    private function executeSendTemplate(array $data, AutomationRun $run, array $context): array
    {
        $name = $data['template_name'] ?? ($data['template_ref'] ?? null);
        if (! $name) {
            return ['status' => 'error', 'message' => 'No template selected.'];
        }
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }

        $components = [];
        // Positional body variables ({{1}}, {{2}}, …). An array preserves blanks so the
        // index alignment is never broken; a legacy newline/comma string is split as a list.
        $vars = is_array($data['variables'] ?? null)
            ? array_map(fn ($v) => (string) $v, $data['variables'])
            : $this->toList($data['variables'] ?? []);
        if (! empty($vars)) {
            $params = array_map(fn ($v) => ['type' => 'text', 'text' => $this->renderTokens($v, $contact, $context)], $vars);
            $components[] = ['type' => 'body', 'parameters' => $params];
        }

        return $this->sendWhatsappPayload($run, 'template', null, ['template' => [
            'name' => $name,
            'language' => $data['language'] ?? 'en',
            'components' => $components,
        ]]);
    }

    private function executeSendMedia(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $type = in_array($data['media_type'] ?? 'image', ['image', 'video', 'document', 'audio'], true) ? $data['media_type'] : 'image';
        $channel = $this->pickChannel($data, $context);

        // Messenger / Instagram drivers only carry image attachments.
        if (in_array($channel, ['messenger', 'instagram'], true) && $type !== 'image') {
            return ['status' => 'skipped', 'message' => ucfirst($channel).' supports image media only.'];
        }

        $link = $this->renderTokens((string) ($data['link'] ?? ''), $contact, $context);
        if ($link === '') {
            return ['status' => 'error', 'message' => 'Media link is required.'];
        }
        $caption = isset($data['caption']) ? $this->renderTokens((string) $data['caption'], $contact, $context) : null;
        $payload = ['link' => $link];
        if ($caption) {
            $payload['caption'] = $caption;
        }
        if (! empty($data['filename'])) {
            $payload['filename'] = (string) $data['filename'];
        }

        return $this->dispatchMessage($run, $channel, $type, $caption, $payload, 'automation', !empty($data['channel_account_id']) ? (int)$data['channel_account_id'] : null);
    }

    private function executeSendSequence(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $steps = $this->parseSteps($data['steps'] ?? []);
        if (empty($steps)) {
            return ['status' => 'skipped', 'message' => 'No steps configured.'];
        }

        $target = $this->resolveChannelTarget((int)$run->automation->workspace_id, $contact, $this->pickChannel($data,$context), !empty($data['channel_account_id'])?(int)$data['channel_account_id']:null);
        if ($target['error']) return ['status'=>'error','message'=>$target['error']];
        foreach ($steps as $index=>$step) {
            $format=$step['kind']==='media'?($step['media_type']??'image'):'text';
            if (!in_array($format,AutomationChannelCapabilities::types($target['account']),true)) return ['status'=>'error','message'=>'A etapa '.($index+1).' usa '.$format.', não suportado pela conta selecionada. Nenhuma etapa desta sequência foi enviada.'];
        }
        $sent = 0;
        foreach ($steps as $step) {
            if ($step['kind'] === 'media') {
                $payload = ['link' => $this->renderTokens($step['link'] ?? '', $contact, $context)];
                if (! empty($step['caption'])) {
                    $payload['caption'] = $this->renderTokens($step['caption'], $contact, $context);
                }
                $res = $this->dispatchMessage($run, $this->pickChannel($data, $context), $step['media_type'] ?? 'image', $payload['caption'] ?? null, $payload, 'automation', !empty($data['channel_account_id']) ? (int)$data['channel_account_id'] : null);
            } else {
                $res = $this->dispatchMessage($run, $this->pickChannel($data, $context), 'text', $this->renderTokens($step['body'] ?? '', $contact, $context), null, 'automation', !empty($data['channel_account_id']) ? (int)$data['channel_account_id'] : null);
            }
            if (($res['status'] ?? '') === 'error') {
                return ['status' => 'error', 'message' => 'Falha na etapa '.($sent + 1).' da sequência: '.$res['message']];
            }
            if (($res['status'] ?? '') === 'ok') {
                $sent++;
            }
        }

        return ['status' => 'ok', 'message' => "Sent {$sent} sequence step(s)."];
    }

    private function executeQuickReplies(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $body = $this->renderTokens((string) ($data['body'] ?? ''), $contact, $context);
        $buttons = $this->toList($data['buttons'] ?? []);
        if ($body === '' || empty($buttons)) {
            return ['status' => 'error', 'message' => 'Body and at least one button are required.'];
        }

        return $this->sendWhatsappPayload($run, 'interactive', $body, ['interactive' => $this->buttonInteractive($body, $buttons)]);
    }

    private function executeListMessage(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $body = $this->renderTokens((string) ($data['body'] ?? ''), $contact, $context);
        $rows = $this->parseRows($data['rows'] ?? []);
        if (trim($body) === '' || empty($rows) || count($rows) > 10) {
            return ['status' => 'error', 'message' => 'Preencha a mensagem e entre 1 e 10 opções para a lista.'];
        }

        $interactive = $this->listInteractive($body, (string) ($data['button_label'] ?? 'Menu'), (string) ($data['section_title'] ?? 'Options'), $rows);

        return $this->sendWhatsappPayload($run, 'interactive', $body, ['interactive' => $interactive]);
    }

    // ─── LISTEN nodes ─────────────────────────────────────────────────────────

    private function executeAskQuestion(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $question = $this->renderTokens((string) ($data['question'] ?? ''), $contact, $context);
        if ($question === '') {
            return ['status' => 'error', 'message' => 'Question text is required.'];
        }

        if (($data['input_type'] ?? '') === 'choice') $question .= "\n".implode("\n", $this->toLines($data['choices'] ?? ''));
        $send = $this->sendTextViaChannel($run, $this->pickChannel($data, $context), $question, 'automation');
        if (($send['status'] ?? '') !== 'ok') {
            // Could not deliver the question (e.g. no open Messenger/Instagram thread) — do not park.
            return $send;
        }

        // Park the run until the contact's next inbound message (see resumeAwaitingReplies()).
        $var = ($data['variable'] ?? '') ?: 'answer';
        $edges = collect($run->automation->edges ?? []);
        $nextEdge = $edges->first(fn ($e) => $e['source'] === $run->current_node_id);
        $run->update(['status' => 'waiting', 'resume_node_id' => $nextEdge['target'] ?? null]);

        return [
            'status' => 'waiting',
            'message' => "Asked question — waiting for reply → {{context.{$var}}}",
            'context_update' => ['_awaiting_reply' => true, '_reply_var' => $var, '_reply_type'=>$data['input_type'] ?? 'text', '_reply_choices'=>$this->toLines($data['choices'] ?? ''), '_reply_conversation'=>$context['conversation_id'] ?? null],
        ];
    }

    // ─── LOGIC nodes ──────────────────────────────────────────────────────────

    private function executeRunSubflow(array $data, AutomationRun $run, array $context): array
    {
        $ref = $data['automation_uuid'] ?? ($data['automation_id'] ?? null);
        if (! $ref) {
            return ['status' => 'error', 'message' => 'No sub-flow selected.'];
        }
        if (! $run->contact_id) {
            return ['status' => 'skipped', 'message' => 'Sub-flows require a contact.'];
        }

        $target = Automation::where('workspace_id', $run->automation->workspace_id)
            ->where(fn ($q) => $q->where('uuid', $ref)->orWhere('id', $ref))
            ->first();

        if (! $target) {
            return ['status' => 'error', 'message' => 'Sub-flow not found.'];
        }
        if ((int) $target->id === (int) $run->automation_id) {
            return ['status' => 'skipped', 'message' => 'A flow cannot call itself.'];
        }
        if (! $target->isActive()) {
            return ['status' => 'skipped', 'message' => 'Sub-flow is not active.'];
        }

        $this->triggerForContact($target, $run->contact_id, $context);

        return ['status' => 'ok', 'message' => "Triggered sub-flow '{$target->name}'."];
    }

    // ─── CONTACT nodes ────────────────────────────────────────────────────────

    private function executeAssignAgent(array $data, AutomationRun $run): array
    {
        if (! $run->contact_id) {
            return ['status' => 'skipped', 'message' => 'No contact to assign.'];
        }
        $workspaceId = $run->automation->workspace_id;
        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->where('contact_id', $run->contact_id)
            ->orderByDesc('last_message_at')
            ->first();

        if (! $conversation) {
            return ['status' => 'skipped', 'message' => 'No conversation found for contact.'];
        }

        $user = null;
        if (! empty($data['user_id'])) {
            $user = User::forWorkspace($workspaceId)->find($data['user_id']);
            if (! $user) {
                return ['status' => 'error', 'message' => 'Assigned user not found in workspace.'];
            }
        }

        $conversation->update([
            'assigned_user_id' => $user?->id,
            'assigned_to' => 'human',
            'handover_at' => now(),
        ]);
        ConversationAssigned::dispatch($conversation, $user);

        return ['status' => 'ok', 'message' => $user ? "Assigned to {$user->name}." : 'Handed off to a human agent.'];
    }

    // ─── ENGAGE nodes ─────────────────────────────────────────────────────────

    private function executeCtaButton(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $body = $this->renderTokens((string) ($data['body'] ?? ''), $contact, $context);
        $url = $this->renderTokens((string) ($data['url'] ?? ''), $contact, $context);
        if ($body === '' || $url === '') {
            return ['status' => 'error', 'message' => 'Body and URL are required.'];
        }

        $interactive = [
            'type' => 'cta_url',
            'body' => ['text' => mb_substr($body, 0, 1024)],
            'action' => [
                'name' => 'cta_url',
                'parameters' => [
                    'display_text' => mb_substr((string) ($data['display_text'] ?? 'Open'), 0, 20),
                    'url' => $url,
                ],
            ],
        ];

        return $this->sendWhatsappPayload($run, 'interactive', $body, ['interactive' => $interactive]);
    }

    private function executeSendLocation(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return ['status' => 'error', 'message' => 'Latitude and longitude are required.'];
        }

        $payload = ['location' => [
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
            'name' => $data['name'] ?? null,
            'address' => $data['address'] ?? null,
        ]];

        return $this->sendWhatsappPayload($run, 'location', $data['name'] ?? null, $payload);
    }

    private function executeSendPoll(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $question = $this->renderTokens((string) ($data['question'] ?? ''), $contact, $context);
        $options = $this->toList($data['options'] ?? []);
        if ($question === '' || empty($options)) {
            return ['status' => 'error', 'message' => 'Question and options are required.'];
        }

        // The Cloud API has no native poll — emulate with reply buttons (≤3) or an interactive list.
        $interactive = count($options) <= 3
            ? $this->buttonInteractive($question, $options)
            : $this->listInteractive($question, (string) ($data['button_label'] ?? 'Vote'), 'Options', array_map(fn ($o) => ['title' => $o, 'description' => ''], $options));

        return $this->sendWhatsappPayload($run, 'interactive', $question, ['interactive' => $interactive]);
    }

    private function executeRunChatbot(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $workspaceId = $run->automation->workspace_id;
        if (empty($data['chatbot_id'])) {
            return ['status' => 'error', 'message' => 'No chatbot selected.'];
        }
        $bot = AiChatbot::where('id', $data['chatbot_id'])->where('workspace_id', $workspaceId)->first();
        if (! $bot || ! $bot->enabled) {
            return ['status' => 'error', 'message' => 'Chatbot not found or disabled.'];
        }

        $message = (string) ($context['message_body'] ?? '');
        if ($message === '' && ! empty($data['prompt'])) {
            $message = $this->renderTokens($data['prompt'], $contact, $context);
        }
        if ($message === '') {
            $message = 'Hello';
        }

        $result = $this->chatbotRunner->runForApi($bot, $message, $workspaceId, $context['history'] ?? []);
        $reply = $result['reply'] ?? null;
        if (! $reply) {
            return ['status' => 'skipped', 'message' => 'Chatbot returned no reply.'];
        }

        $channel = $this->pickChannel($data, $context);
        $send = $this->sendTextViaChannel($run, $channel, $reply, 'bot');
        if (($send['status'] ?? 'error') === 'ok') {
            foreach ($result['product_ids'] ?? [] as $productId) {
                $this->sendCatalogProduct($run, $channel, (int) $productId);
            }
        }

        return [
            'status' => $send['status'] ?? 'ok',
            'message' => ($send['status'] ?? 'ok') === 'ok' ? 'Chatbot reply sent.' : $send['message'],
            'output' => ['reply' => $reply, 'tokens_used' => $result['tokens_used'] ?? 0],
            'context_update' => ['last_ai_reply' => $reply],
        ];
    }

    private function executeBookAppointment(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contato não encontrado.'];
        }
        if (($data['booking_mode'] ?? 'local') === 'local') {
            return $this->executeLocalAppointment($data, $run, $context, $contact);
        }
        $google = GoogleClient::resolve();
        if (! $google) {
            return ['status' => 'error', 'message' => 'A integração com o Google Workspace não está configurada.'];
        }

        $start = $this->parseDateTime($this->renderTokens((string) ($data['start'] ?? ''), $contact, $context));
        if (! $start) {
            return ['status' => 'error', 'message' => 'Informe uma data e hora de início válidas.'];
        }
        $duration = max(1, (int) ($data['duration_minutes'] ?? 30));
        $summary = $this->renderTokens((string) ($data['summary'] ?? 'Appointment'), $contact, $context);
        $description = isset($data['description']) ? $this->renderTokens((string) $data['description'], $contact, $context) : null;

        try {
            $res = $google->createCalendarEvent(
                ($data['calendar_id'] ?? '') ?: 'primary',
                $summary,
                $start->toRfc3339String(),
                $start->copy()->addMinutes($duration)->toRfc3339String(),
                $contact->email ? [$contact->email] : [],
                false,
                $description,
                ($data['timezone'] ?? '') ?: null,
            );
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $ctx = ['appointment_link' => $res['html_link'], 'appointment_event_id' => $res['event_id']];

        if (! empty($data['send_confirmation'])) {
            $msg = "✅ Seu agendamento \"{$summary}\" foi confirmado para ".$start->format('d/m/Y H:i').'.';
            if ($res['html_link']) {
                $msg .= "\n".$res['html_link'];
            }
            $this->sendTextViaChannel($run, $this->pickChannel($data, $context), $msg, 'automation');
        }

        return ['status' => 'ok', 'message' => 'Agendamento criado para '.$start->format('d/m/Y H:i').'.', 'output' => $ctx, 'context_update' => $ctx];
    }

    private function executeLocalAppointment(array $data, AutomationRun $run, array $context, Contact $contact): array
    {
        $workspaceId = (int) $run->automation->workspace_id;
        $service = AppointmentService::where('workspace_id', $workspaceId)
            ->where('uuid', $data['service_uuid'] ?? '')->where('active', true)->first();
        $resource = AppointmentResource::where('workspace_id', $workspaceId)
            ->where('uuid', $data['resource_uuid'] ?? '')->where('active', true)->first();
        if (! $service || ! $resource) {
            return ['status' => 'error', 'message' => 'Selecione um serviço e um profissional ativos.'];
        }

        $renderedStart = $this->renderTokens((string) ($data['start'] ?? ''), $contact, $context);
        try {
            $start = Carbon::parse($renderedStart, $resource->timezone)->utc();
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => 'A data e hora do agendamento são inválidas.'];
        }
        if ($start->isPast()) {
            return ['status' => 'error', 'message' => 'O agendamento precisa estar no futuro.'];
        }
        $end = $start->copy()->addMinutes($service->duration_minutes + $service->buffer_minutes);
        $summary = $this->renderTokens((string) ($data['summary'] ?? $service->name), $contact, $context);

        try {
            $appointment = DB::transaction(function () use ($workspaceId, $service, $resource, $contact, $data, $start, $end, $summary) {
                $conflict = Appointment::where('resource_id', $resource->id)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
                    ->lockForUpdate()->exists();
                if ($conflict) {
                    throw new \RuntimeException('Este horário já está ocupado.');
                }

                return Appointment::create([
                    'workspace_id' => $workspaceId,
                    'service_id' => $service->id,
                    'resource_id' => $resource->id,
                    'contact_id' => $contact->id,
                    'customer_name' => $contact->full_name ?: 'Cliente',
                    'customer_email' => $contact->email,
                    'customer_phone' => $contact->phone_e164,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'timezone' => $resource->timezone,
                    'notes' => $summary,
                    'status' => 'scheduled',
                    'source' => 'automation',
                    'customer_reminder_at' => ! empty($data['customer_reminder_minutes']) ? $start->copy()->subMinutes((int) $data['customer_reminder_minutes']) : null,
                    'professional_reminder_at' => ! empty($data['professional_reminder_minutes']) ? $start->copy()->subMinutes((int) $data['professional_reminder_minutes']) : null,
                ]);
            });
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        if (! empty($data['send_confirmation'])) {
            $when = $start->timezone($resource->timezone)->format('d/m/Y H:i');
            $this->sendTextViaChannel($run, $this->pickChannel($data, $context), "✅ Agendamento confirmado: {$service->name}, dia {$when}.", 'automation');
        }

        $ctx = ['appointment_uuid' => $appointment->uuid];
        return ['status' => 'ok', 'message' => 'Agendamento criado na agenda do Respondesk.', 'output' => $ctx, 'context_update' => $ctx];
    }

    private function executeGoogleMeet(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $google = GoogleClient::resolve();
        if (! $google) {
            return ['status' => 'error', 'message' => 'A integração com o Google Workspace não está configurada.'];
        }

        $start = $this->parseDateTime($this->renderTokens((string) ($data['start'] ?? ''), $contact, $context));
        if (! $start) {
            return ['status' => 'error', 'message' => 'A valid start date/time is required.'];
        }
        $duration = max(1, (int) ($data['duration_minutes'] ?? 30));
        $summary = $this->renderTokens((string) ($data['summary'] ?? 'Meeting'), $contact, $context);

        try {
            $res = $google->createCalendarEvent(
                ($data['calendar_id'] ?? '') ?: 'primary',
                $summary,
                $start->toRfc3339String(),
                $start->copy()->addMinutes($duration)->toRfc3339String(),
                $contact->email ? [$contact->email] : [],
                true,
                null,
                ($data['timezone'] ?? '') ?: null,
            );
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $meet = $res['meet_url'] ?? null;
        if (! $meet) {
            return ['status' => 'error', 'message' => 'Meet link was not created. Ensure the calendar can create conferences.'];
        }

        $ctx = ['meet_url' => $meet, 'appointment_event_id' => $res['event_id']];

        if ($data['send_link'] ?? true) {
            $msg = "📹 Join your meeting \"{$summary}\" (".$start->format('M j, g:i A')."):\n".$meet;
            $this->sendTextViaChannel($run, $data['channel'] ?? 'whatsapp', $msg, 'automation');
        }

        return ['status' => 'ok', 'message' => 'Google Meet created.', 'output' => $ctx, 'context_update' => $ctx];
    }

    private function executeWhatsappForm(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $flowId = (string) ($data['flow_id'] ?? '');
        if ($flowId === '') {
            return ['status' => 'error', 'message' => 'Flow ID is required.'];
        }
        $body = $this->renderTokens((string) ($data['body'] ?? ''), $contact, $context);
        $cta = (string) ($data['flow_cta'] ?? 'Open form');

        $params = [
            'flow_message_version' => '3',
            'flow_id' => $flowId,
            'flow_cta' => mb_substr($cta, 0, 20),
            'flow_action' => 'navigate',
            'flow_token' => (string) (($data['flow_token'] ?? '') ?: 'flow_'.$run->id),
        ];
        if (! empty($data['screen'])) {
            $params['flow_action_payload'] = ['screen' => (string) $data['screen']];
        }

        $interactive = [
            'type' => 'flow',
            'body' => ['text' => mb_substr($body !== '' ? $body : $cta, 0, 1024)],
            'action' => ['name' => 'flow', 'parameters' => $params],
        ];

        return $this->sendWhatsappPayload($run, 'interactive', $body, ['interactive' => $interactive]);
    }

    // ─── COMMERCE nodes ───────────────────────────────────────────────────────

    private function executeWhatsappCatalog(array $data, AutomationRun $run, array $context): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $body = $this->renderTokens((string) ($data['body'] ?? 'Browse our catalog'), $contact, $context);

        $action = ['name' => 'catalog_message'];
        if (! empty($data['thumbnail_product_retailer_id'])) {
            $action['parameters'] = ['thumbnail_product_retailer_id' => (string) $data['thumbnail_product_retailer_id']];
        }

        $interactive = ['type' => 'catalog_message', 'body' => ['text' => mb_substr($body, 0, 1024)], 'action' => $action];

        return $this->sendWhatsappPayload($run, 'interactive', $body, ['interactive' => $interactive]);
    }

    private function executeSendProduct(array $data, AutomationRun $run, array $context, string $platform): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        $workspaceId = $run->automation->workspace_id;

        $query = EcommerceProduct::where('workspace_id', $workspaceId)->where('platform', $platform);
        if (! empty($data['store_id'])) {
            $query->where('store_id', $data['store_id']);
        }
        if (! empty($data['product_id'])) {
            $query->where(fn ($w) => $w->where('id', $data['product_id'])->orWhere('external_id', (string) $data['product_id']));
        } elseif (! empty($data['external_id'])) {
            $query->where('external_id', (string) $data['external_id']);
        } else {
            return ['status' => 'error', 'message' => 'No product selected.'];
        }

        $product = $query->first();
        if (! $product) {
            return ['status' => 'error', 'message' => 'Product not found. Sync your store products first.'];
        }

        $url = $product->raw['permalink'] ?? ($product->raw['onlineStoreUrl'] ?? null);
        $lines = array_filter([
            '*'.$product->name.'*',
            $product->price !== null ? 'Price: '.number_format((float) $product->price, 2) : null,
            $product->sku ? 'SKU: '.$product->sku : null,
            $url,
        ]);
        $caption = implode("\n", $lines);
        if (! empty($data['body'])) {
            $caption = $this->renderTokens((string) $data['body'], $contact, $context)."\n\n".$caption;
        }

        $channel = $this->pickChannel($data, $context);
        if ($product->image_url) {
            return $this->dispatchMessage($run, $channel, 'image', $caption, ['link' => $product->image_url, 'caption' => $caption]);
        }

        return $this->dispatchMessage($run, $channel, 'text', $caption, null);
    }

    // ─── INTEGRATIONS nodes ───────────────────────────────────────────────────

    private function executeGoogleSheets(array $data, AutomationRun $run, array $context): array
    {
        $google = GoogleClient::resolve();
        if (! $google) {
            return ['status' => 'error', 'message' => 'A integração com o Google Workspace não está configurada.'];
        }
        $spreadsheetId = (string) ($data['spreadsheet_id'] ?? '');
        $range = (string) ($data['range'] ?? '');
        if ($spreadsheetId === '' || $range === '') {
            return ['status' => 'error', 'message' => 'Spreadsheet ID and range are required.'];
        }
        $contact = $run->contact_id ? Contact::find($run->contact_id) : null;

        try {
            if (($data['mode'] ?? 'append') === 'read') {
                $rows = $google->readSheetRange($spreadsheetId, $range);
                $var = ($data['result_var'] ?? '') ?: 'sheet';

                return [
                    'status' => 'ok',
                    'message' => 'Read '.count($rows).' row(s) from sheet.',
                    'output' => ['rows' => $rows],
                    'context_update' => [$var => (string) ($rows[0][0] ?? ''), $var.'_json' => json_encode($rows)],
                ];
            }

            $values = array_map(
                fn ($v) => $contact ? $this->renderTokens(trim($v), $contact, $context) : trim($v),
                $this->toLines($data['values'] ?? [])
            );
            $res = $google->appendSheetRow($spreadsheetId, $range, $values);

            return ['status' => 'ok', 'message' => 'Appended row to sheet.', 'output' => $res];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function executeGoogleDocs(array $data, AutomationRun $run, array $context): array
    {
        $google = GoogleClient::resolve();
        if (! $google) {
            return ['status' => 'error', 'message' => 'A integração com o Google Workspace não está configurada.'];
        }
        $templateId = (string) ($data['template_doc_id'] ?? '');
        if ($templateId === '') {
            return ['status' => 'error', 'message' => 'Template document ID is required.'];
        }
        $contact = $run->contact_id ? Contact::find($run->contact_id) : null;
        $title = $contact ? $this->renderTokens((string) ($data['title'] ?? 'Document'), $contact, $context) : (string) ($data['title'] ?? 'Document');
        $replacements = $this->parseReplacements($data['replacements'] ?? [], $contact, $context);

        try {
            $res = $google->createDocFromTemplate($templateId, $title ?: 'Document', $replacements);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $ctx = ['doc_url' => $res['url'], 'doc_id' => $res['doc_id']];
        if (! empty($data['send_link'])) {
            $this->sendTextViaChannel($run, $data['channel'] ?? 'whatsapp', "📄 {$title}:\n".$res['url'], 'automation');
        }

        return ['status' => 'ok', 'message' => 'Document generated.', 'output' => $ctx, 'context_update' => $ctx];
    }

    /**
     * Google Forms node. Two modes:
     *   - send_link (default) → share the form's responder URL with the contact.
     *   - read_response       → pull the latest submission's answers into the run context.
     */
    private function executeGoogleForms(array $data, AutomationRun $run, array $context): array
    {
        $google = GoogleClient::resolve();
        if (! $google) {
            return ['status' => 'error', 'message' => 'A integração com o Google Workspace não está configurada.'];
        }
        $formId = (string) ($data['form_id'] ?? '');
        if ($formId === '') {
            return ['status' => 'error', 'message' => 'Form ID is required.'];
        }
        $contact = $run->contact_id ? Contact::find($run->contact_id) : null;
        $mode = ($data['mode'] ?? 'send_link') === 'read_response' ? 'read_response' : 'send_link';

        try {
            if ($mode === 'read_response') {
                $responses = $google->listFormResponses($formId);
                if (empty($responses)) {
                    return ['status' => 'skipped', 'message' => 'No form responses yet.'];
                }

                $latest = $responses[0];
                $var = ($data['result_var'] ?? '') ?: 'form';
                $answers = [];
                foreach ($latest['answers'] ?? [] as $questionId => $answer) {
                    $values = array_map(fn ($a) => $a['value'] ?? '', $answer['textAnswers']['answers'] ?? []);
                    $answers[$questionId] = implode(', ', $values);
                }

                return [
                    'status' => 'ok',
                    'message' => 'Read latest form response.',
                    'output' => ['response_id' => $latest['responseId'] ?? null, 'answers' => $answers],
                    'context_update' => [
                        $var.'_id' => $latest['responseId'] ?? '',
                        $var.'_json' => json_encode($answers),
                    ],
                ];
            }

            $form = $google->getForm($formId);
            $url = (string) ($form['responderUri'] ?? '');
            if ($url === '') {
                return ['status' => 'error', 'message' => 'Form has no shareable responder link.'];
            }
            $title = $form['info']['title'] ?? 'Form';
            $ctx = ['form_url' => $url, 'form_title' => $title];

            if (! empty($data['send_link']) && $contact) {
                $body = ! empty($data['body'])
                    ? $this->renderTokens((string) $data['body'], $contact, $context)
                    : "📋 {$title}";
                $this->sendTextViaChannel($run, $data['channel'] ?? 'whatsapp', $body."\n".$url, 'automation');
            }

            return ['status' => 'ok', 'message' => 'Fetched form link.', 'output' => $ctx, 'context_update' => $ctx];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ─── Shared send helpers ──────────────────────────────────────────────────

    /** Use the channel selected on the node or, by default, the channel that received the trigger. */
    private function pickChannel(array $data, array $context = []): string
    {
        $channel = $data['channel'] ?? $context['message_channel'] ?? 'whatsapp';

        return $channel;
    }

    /**
     * Resolve which channel account + conversation to use for an outbound message on
     * $channel. Prefers the contact's most-recent conversation on that channel so that,
     * with multiple accounts (e.g. two WhatsApp numbers / two Pages), replies go out on
     * the same account/thread the contact already uses. Messenger & Instagram can ONLY be
     * messaged inside an existing thread (the PSID/IGSID lives on conversation.external_thread_id).
     *
     * @return array{account: ?ChannelAccount, conversation: ?Conversation, error: ?string, soft: bool}
     */
    private function resolveChannelTarget(int $workspaceId, Contact $contact, string $channel, ?int $accountId = null): array
    {
        if ($accountId !== null) {
            $account = ChannelAccount::where('workspace_id', $workspaceId)
                ->where('channel', $channel)->where('status', 'active')->find($accountId);
            if (! $account) {
                return ['account' => null, 'conversation' => null, 'soft' => false, 'error' => 'A conta de envio selecionada está indisponível ou não pertence a este canal e espaço de trabalho.'];
            }
            $conversation = Conversation::where('workspace_id', $workspaceId)
                ->where('contact_id', $contact->id)->where('channel_account_id', $account->id)
                ->orderByDesc('last_message_at')->first();
            if (! $conversation && in_array($channel, ['messenger', 'instagram'], true)) {
                return ['account' => null, 'conversation' => null, 'soft' => false, 'error' => 'Este contato precisa iniciar uma conversa com a conta selecionada antes do envio.'];
            }
            return ['account' => $account, 'conversation' => $conversation, 'soft' => false, 'error' => null];
        }

        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->whereHas('channelAccount', fn ($q) => $q->where('channel', $channel)->where('status', 'active'))
            ->orderByDesc('last_message_at')
            ->first();

        if ($conversation) {
            return ['account' => $conversation->channelAccount, 'conversation' => $conversation, 'error' => null, 'soft' => false];
        }

        // No existing thread. Messenger/Instagram cannot be initiated proactively.
        if (in_array($channel, ['messenger', 'instagram'], true)) {
            return [
                'account' => null, 'conversation' => null, 'soft' => true,
                'error' => "No open {$channel} conversation with this contact — a {$channel} thread can only start after the contact messages first.",
            ];
        }

        $account = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', $channel)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $account) {
            return ['account' => null, 'conversation' => null, 'soft' => false, 'error' => "No active {$channel} channel account in this workspace."];
        }

        return ['account' => $account, 'conversation' => null, 'error' => null, 'soft' => false];
    }

    /**
     * Create + send an outbound message on any supported channel
     * (whatsapp / messenger / instagram / telegram / sms), routed to the correct account.
     */
    private function dispatchMessage(AutomationRun $run, string $channel, string $type, ?string $body, ?array $payload, string $sentBy = 'automation', ?int $accountId = null): array
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return ['status' => 'skipped', 'message' => 'Contact not found.'];
        }
        if (! in_array($channel, ['whatsapp', 'messenger', 'instagram', 'telegram', 'sms'], true)) {
            return ['status' => 'error', 'message' => 'Este canal ainda não tem ação de envio direto neste bloco. Escolha explicitamente um canal de envio compatível.'];
        }

        if ($channel === 'sms') {
            return $this->dispatchSms($run, $contact, $body ?? '', $sentBy);
        }

        if ($accountId === null) {
            $nodeData=collect($run->automation->nodes ?? [])->firstWhere('id',$run->current_node_id)['data'] ?? [];
            if (!empty($nodeData['channel_account_id'])) $accountId=(int)$nodeData['channel_account_id'];
        }
        $target = $this->resolveChannelTarget($run->automation->workspace_id, $contact, $channel, $accountId);
        if ($target['error']) {
            return ['status' => $target['soft'] ? 'skipped' : 'error', 'message' => $target['error']];
        }

        $account = $target['account'];
        if (!AutomationChannelCapabilities::supports($account, $type, $payload)) return ['status'=>'error','message'=>'O formato '.$type.' não é suportado pela conta '.($account->display_name ?: $account->provider).'. Escolha uma conta compatível ou uma mensagem de texto.'];
        $conversation = $target['conversation'] ?? $this->resolveOrCreateConversation($contact, $account, $channel);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => $channel,
            'type' => $type,
            'body' => $body,
            'payload' => $payload,
            'status' => 'queued',
            'sent_by' => $sentBy,
            'sent_at' => now(),
        ]);

        try {
            $messageId = $this->channelManager->driver($channel)->send($message);
            $message->update(['status' => 'sent', 'provider_message_id' => $messageId]);
        } catch (\Throwable $e) {
            $message->update(['status' => 'failed', 'error_json' => ['message' => $e->getMessage()]]);

            return ['status' => 'error', 'message' => ucfirst($channel).' send failed: '.$e->getMessage()];
        }

        $conversation->update(['last_message_at' => now()]);
        $message->load('conversation');
        MessageSent::dispatch($message);

        return ['status' => 'ok', 'message' => ucfirst($channel).' message sent.', 'output' => ['message_id' => $message->id]];
    }

    /** Send an SMS via the workspace's configured SMS provider (Broadcasting drivers). */
    private function dispatchSms(AutomationRun $run, Contact $contact, string $text, string $sentBy): array
    {
        if (! $contact->phone_e164) {
            return ['status' => 'skipped', 'message' => 'Contact has no phone number for SMS.'];
        }
        $workspaceId = $run->automation->workspace_id;

        try {
            $driver = SmsDriverManager::forWorkspace($workspaceId);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $account = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'sms')
            ->where('status', 'active')
            ->first();
        $conversation = $account
            ? $this->resolveOrCreateConversation($contact, $account, 'sms')
            : Conversation::firstOrCreate(
                ['workspace_id' => $workspaceId, 'contact_id' => $contact->id, 'channel_account_id' => null],
                ['status' => 'open', 'unread_count' => 0, 'external_thread_id' => $contact->phone_e164],
            );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => 'sms',
            'type' => 'text',
            'body' => $text,
            'status' => 'queued',
            'sent_by' => $sentBy,
            'sent_at' => now(),
        ]);

        try {
            $result = $driver->send($contact->phone_e164, $text);
            if (! $result->success) {
                throw new \RuntimeException($result->error ?: 'SMS provider rejected the message.');
            }
            $message->update(['status' => 'sent', 'provider_message_id' => $result->messageId]);
        } catch (\Throwable $e) {
            $message->update(['status' => 'failed', 'error_json' => ['message' => $e->getMessage()]]);

            return ['status' => 'error', 'message' => 'SMS send failed: '.$e->getMessage()];
        }

        $conversation->update(['last_message_at' => now()]);
        $message->load('conversation');
        MessageSent::dispatch($message);

        return ['status' => 'ok', 'message' => 'SMS sent.', 'output' => ['message_id' => $message->id]];
    }

    /** WhatsApp-only send (templates, interactive, media, location) — routed to the right account. */
    private function sendWhatsappPayload(AutomationRun $run, string $type, ?string $body, ?array $payload, string $sentBy = 'automation'): array
    {
        return $this->dispatchMessage($run, 'whatsapp', $type, $body, $payload, $sentBy);
    }

    /** Send a plain text message on the given channel (whatsapp / messenger / instagram / telegram / sms). */
    private function sendTextViaChannel(AutomationRun $run, string $channel, string $text, string $sentBy): array
    {
        return $this->dispatchMessage($run, $channel, 'text', $text, null, $sentBy);
    }

    /**
     * Send an AI-selected product using the same rich image/caption shape as the inbox picker.
     * Availability is checked again immediately before sending.
     */
    private function sendCatalogProduct(AutomationRun $run, string $channel, int $productId): array
    {
        $product = EcommerceProduct::query()
            ->with('store:id,domain,external_meta,status')
            ->where('workspace_id', $run->automation->workspace_id)
            ->where('id', $productId)
            ->where(fn ($stock) => $stock->whereNull('inventory_quantity')->orWhere('inventory_quantity', '>', 0))
            ->whereHas('store', fn ($store) => $store->where('status', 'connected'))
            ->first();

        if (! $product) {
            return ['status' => 'skipped', 'message' => 'Produto indisponível ou fora de estoque.'];
        }

        $currency = strtoupper((string) ($product->store?->external_meta['currency'] ?? 'BRL'));
        $prefix = $currency === 'BRL' ? 'R$ ' : $currency.' ';
        $lines = [
            '🛍️ '.($channel === 'whatsapp' ? '*'.$product->name.'*' : $product->name),
            'Preço: '.$prefix.number_format((float) $product->price, 2, ',', '.'),
        ];
        if ($product->sku) {
            $lines[] = 'Código/SKU: '.$product->sku;
        }

        $raw = $product->raw ?? [];
        $url = match ($product->platform) {
            'shopify' => $raw['online_store_url']
                ?? (! empty($raw['handle']) && $product->store?->domain ? 'https://'.$product->store->domain.'/products/'.$raw['handle'] : null),
            'woocommerce' => $raw['permalink'] ?? null,
            default => $raw['custom_url']['url'] ?? $raw['url'] ?? null,
        };
        if ($url) {
            $lines[] = $url;
        }

        $caption = implode("\n", $lines);
        $payload = [
            'content_kind' => 'product_share',
            'product_id' => $product->id,
        ];
        if ($product->image_url) {
            $payload += [
                'link' => $product->image_url,
                'preview_url' => $product->image_url,
                'caption' => $caption,
            ];
        }

        return $this->dispatchMessage(
            $run,
            $channel,
            $product->image_url ? 'image' : 'text',
            $caption,
            $payload,
            'bot',
        );
    }

    /** WhatsApp interactive reply-buttons payload (max 3). */
    private function buttonInteractive(string $body, array $titles): array
    {
        $buttons = [];
        foreach (array_slice(array_values($titles), 0, 3) as $i => $title) {
            $buttons[] = ['type' => 'reply', 'reply' => ['id' => 'btn_'.($i + 1), 'title' => mb_substr((string) $title, 0, 20)]];
        }

        return ['type' => 'button', 'body' => ['text' => mb_substr($body, 0, 1024)], 'action' => ['buttons' => $buttons]];
    }

    /** WhatsApp interactive list payload (max 10 rows). */
    private function listInteractive(string $body, string $buttonLabel, string $sectionTitle, array $rows): array
    {
        $items = [];
        foreach (array_slice(array_values($rows), 0, 10) as $i => $row) {
            $item = ['id' => 'row_'.($i + 1), 'title' => mb_substr($row['title'] ?? '', 0, 24)];
            if (! empty($row['description'])) {
                $item['description'] = mb_substr($row['description'], 0, 72);
            }
            $items[] = $item;
        }

        return [
            'type' => 'list',
            'body' => ['text' => mb_substr($body, 0, 1024)],
            'action' => [
                'button' => mb_substr($buttonLabel ?: 'Menu', 0, 20),
                'sections' => [['title' => mb_substr($sectionTitle ?: 'Options', 0, 24), 'rows' => $items]],
            ],
        ];
    }

    // ─── Parsing helpers ──────────────────────────────────────────────────────

    /** Normalise a value to a trimmed list (accepts an array, or a comma/newline string). */
    private function toList(mixed $v): array
    {
        if (is_array($v)) {
            return array_values(array_filter(array_map(fn ($x) => trim((string) $x), $v), fn ($x) => $x !== ''));
        }
        if (is_string($v) && $v !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $v) ?: []), fn ($x) => $x !== ''));
        }

        return [];
    }

    /** Split a value into lines, preserving empty cells for column alignment. */
    private function toLines(mixed $v): array
    {
        if (is_array($v)) {
            return array_map(fn ($x) => (string) $x, $v);
        }

        return preg_split('/\r\n|\r|\n/', (string) $v) ?: [];
    }

    /**
     * Parse list rows. Accepts an array of {title, description} objects/strings, or a
     * newline string where each line is "Title|Description".
     *
     * @return list<array{title: string, description: string}>
     */
    private function parseRows(mixed $v): array
    {
        $rows = [];
        if (is_array($v)) {
            foreach ($v as $r) {
                if (is_array($r)) {
                    $title = trim((string) ($r['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $rows[] = ['title' => $title, 'description' => trim((string) ($r['description'] ?? ''))];
                } else {
                    $title = trim((string) $r);
                    if ($title !== '') {
                        $rows[] = ['title' => $title, 'description' => ''];
                    }
                }
            }
        } elseif (is_string($v)) {
            foreach (preg_split('/\r\n|\r|\n/', $v) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                [$title, $desc] = array_pad(explode('|', $line, 2), 2, '');
                $title = trim($title);
                if ($title !== '') {
                    $rows[] = ['title' => $title, 'description' => trim($desc)];
                }
            }
        }

        return $rows;
    }

    /**
     * Parse sequence steps. Accepts an array of {kind, body, media_type, link, caption}
     * objects, or a newline string ("text|...", "image|url|caption").
     *
     * @return list<array<string, mixed>>
     */
    private function parseSteps(mixed $v): array
    {
        $steps = [];
        if (is_array($v)) {
            foreach ($v as $s) {
                if (! is_array($s)) {
                    $t = trim((string) $s);
                    if ($t !== '') {
                        $steps[] = ['kind' => 'text', 'body' => $t];
                    }

                    continue;
                }
                $kind = ($s['kind'] ?? 'text') === 'media' ? 'media' : 'text';
                if ($kind === 'media') {
                    if (empty($s['link'])) {
                        continue;
                    }
                    $steps[] = [
                        'kind' => 'media',
                        'media_type' => in_array($s['media_type'] ?? 'image', ['image', 'video', 'document', 'audio'], true) ? $s['media_type'] : 'image',
                        'link' => (string) $s['link'],
                        'caption' => $s['caption'] ?? null,
                    ];
                } elseif (trim((string) ($s['body'] ?? '')) !== '') {
                    $steps[] = ['kind' => 'text', 'body' => (string) $s['body']];
                }
            }
        } elseif (is_string($v)) {
            foreach (preg_split('/\r\n|\r|\n/', $v) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = explode('|', $line);
                $head = strtolower(trim($parts[0]));
                if (in_array($head, ['image', 'video', 'document', 'audio'], true)) {
                    $steps[] = ['kind' => 'media', 'media_type' => $head, 'link' => trim($parts[1] ?? ''), 'caption' => isset($parts[2]) ? trim($parts[2]) : null];
                } else {
                    $body = $head === 'text' ? trim(substr($line, strpos($line, '|') + 1)) : $line;
                    if ($body !== '') {
                        $steps[] = ['kind' => 'text', 'body' => $body];
                    }
                }
            }
        }

        return $steps;
    }

    /**
     * Parse Doc placeholder replacements. Accepts an array of {key, value}, an assoc
     * array, a JSON object string, or "key=value" lines. Values are token-rendered.
     *
     * @return array<string, string>
     */
    private function parseReplacements(mixed $v, ?Contact $contact, array $context): array
    {
        $out = [];
        $render = fn ($s) => $contact ? $this->renderTokens((string) $s, $contact, $context) : (string) $s;

        if (is_array($v)) {
            foreach ($v as $k => $item) {
                if (is_array($item) && isset($item['key'])) {
                    $out[(string) $item['key']] = $render($item['value'] ?? '');
                } elseif (is_string($k)) {
                    $out[$k] = $render($item);
                }
            }
        } elseif (is_string($v) && trim($v) !== '') {
            $decoded = json_decode($v, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $val) {
                    $out[(string) $k] = $render($val);
                }
            } else {
                foreach (preg_split('/\r\n|\r|\n/', $v) ?: [] as $line) {
                    if (! str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $val] = array_pad(explode('=', $line, 2), 2, '');
                    $k = trim($k);
                    if ($k !== '') {
                        $out[$k] = $render(trim($val));
                    }
                }
            }
        }

        return $out;
    }

    private function parseDateTime(?string $v): ?Carbon
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return Carbon::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }
}
