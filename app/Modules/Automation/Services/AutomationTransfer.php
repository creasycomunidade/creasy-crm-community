<?php

namespace App\Modules\Automation\Services;

use App\Modules\Automation\Models\Automation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AutomationTransfer
{
    private const EXISTING = ['trigger', 'send_whatsapp',  'send_email', 'send_template', 'send_media', 'send_sequence', 'quick_replies', 'list_message', 'ask_question', 'wait', 'condition', 'webhook', 'run_subflow', 'ai_reply', 'add_tag', 'remove_tag', 'update_contact', 'assign_agent',  'cta_button', 'send_location', 'send_poll', 'run_chatbot', 'book_appointment', 'google_meet', 'whatsapp_form', 'whatsapp_catalog',   'google_sheets', 'google_docs', 'google_forms'];

    public function export(Automation $automation): array
    {
        return ['format' => 'respondesk.automation', 'version' => 1, 'automation' => [
            'name' => $automation->name, 'trigger_type' => $automation->trigger_type,
            'trigger_config' => $this->clean($automation->trigger_config ?? []),
            'nodes' => $this->clean($automation->nodes ?? []), 'edges' => $automation->edges ?? [],
        ]];
    }

    private function clean(array $value): array
    {
        foreach ($value as $key => $item) {
            if (preg_match('/token|secret|password|authorization|api.?key|headers/i', (string) $key)) {
                unset($value[$key]);

                continue;
            }
            if (is_array($item)) {
                $value[$key] = $this->clean($item);
            } elseif (is_string($item) && preg_match('~^https?://~i', $item) && (parse_url($item, PHP_URL_QUERY) || parse_url($item, PHP_URL_USER))) {
                // URLs may embed credentials; require reconfiguration instead of exporting them.
                $value[$key] = '';
            }
        }

        return $value;
    }

    public function parse(array $document): array
    {
        Validator::make($document, [
            'format' => ['required', 'in:respondesk.automation'], 'version' => ['required', 'integer', 'in:1'],
            'automation' => ['required', 'array'], 'automation.name' => ['required', 'string', 'max:128'],
            'automation.trigger_type' => ['nullable', 'string', 'max:64'],
            'automation.trigger_config' => ['nullable', 'array'],
            'automation.nodes' => ['required', 'array', 'min:1', 'max:200'],
            'automation.nodes.*.id' => ['required', 'string', 'max:64', 'distinct'],
            'automation.nodes.*.type' => ['required', 'string'],
            'automation.nodes.*.data' => ['present', 'array'],
            'automation.edges' => ['present', 'array', 'max:400'],
            'automation.edges.*.source' => ['required', 'string'],
            'automation.edges.*.target' => ['required', 'string'],
        ])->validate();
        $auto = $document['automation'];
        $allowed = array_merge(self::EXISTING, array_keys(ExtendedAutomationNodes::catalog()));
        $nodes = [];
        $ids = [];
        $triggers = 0;
        $references = ['channel_account_id', 'account_key', 'chatbot_id', 'campaign_id', 'user_id', 'agent_id', 'assigned_user_id', 'automation_uuid', 'stage_id', 'column_id', 'segment_id', 'social_account_id', 'social_post_id', 'store_id', 'product_id', 'service_id', 'resource_id', 'appointment_service_id', 'appointment_resource_id'];
        foreach ($auto['nodes'] as $n) {
            $type = $n['data']['nodeType'] ?? $n['type'];
            if ($type === 'triggerNode') {
                $type = 'trigger';
            }
            if (! in_array($type, $allowed, true)) {
                throw ValidationException::withMessages(['file' => 'O arquivo contém um nó não suportado: '.$type]);
            }
            if ($type === 'trigger') {
                $triggers++;
            }
            $data = $this->clean($n['data']);
            foreach ($references as $key) {
                if (array_key_exists($key, $data)) {
                    $data[$key] = '';
                }
            }
            unset($data['agent_name'],$data['subflow_name']);
            if ($type !== 'trigger') {
                unset($data['triggerType']);
            }
            $data['nodeType'] = $type;
            $data['configured'] = false;
            $nodes[] = ['id' => $n['id'], 'type' => $type, 'data' => $data, 'position' => ['x' => (float) ($n['position']['x'] ?? 250), 'y' => (float) ($n['position']['y'] ?? count($nodes) * 130)]];
            $ids[] = $n['id'];
        }
        if ($triggers !== 1) {
            throw ValidationException::withMessages(['file' => 'A automação precisa ter exatamente um gatilho.']);
        }
        $edges = [];
        foreach ($auto['edges'] as $i => $e) {
            if (! in_array($e['source'], $ids, true) || ! in_array($e['target'], $ids, true)) {
                throw ValidationException::withMessages(['file' => 'O arquivo contém conexões para nós inexistentes.']);
            }
            $edges[] = ['id' => 'edge-'.$i, 'source' => $e['source'], 'target' => $e['target'], 'sourceHandle' => in_array($e['sourceHandle'] ?? null, ['true', 'false'], true) ? $e['sourceHandle'] : null];
        }
        $config = $this->clean($auto['trigger_config'] ?? []);
        unset($config['account_key']);

        return ['name' => $auto['name'], 'status' => 'draft', 'trigger_type' => $auto['trigger_type'] ?? null, 'trigger_config' => $config, 'nodes' => $nodes, 'edges' => $edges];
    }
}
