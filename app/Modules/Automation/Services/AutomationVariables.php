<?php

namespace App\Modules\Automation\Services;

use App\Modules\Shared\Models\Contact;

class AutomationVariables
{
    public static function render(string $template, ?Contact $contact, array $context): string
    {
        $fields = $contact ? $contact->only(['id', 'first_name', 'last_name', 'email', 'phone_e164', 'country', 'language', 'source', 'opt_in_whatsapp', 'opt_in_sms', 'opt_in_email', 'custom_fields']) : [];
        $fields['name'] = $contact?->full_name ?? '';
        $fields['phone'] = $contact?->phone_e164 ?? '';
        $values = ['contact' => $fields, 'context' => $context, 'message' => ['body' => $context['message_body'] ?? '', 'channel' => $context['message_channel'] ?? '']];

        return preg_replace_callback('/\{\{\s*((?:contact|context|message)\.[\w.]+)\s*\}\}/u', function ($m) use ($values) {
            $value = data_get($values, $m[1], '');

            return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }, $template);
    }
}
