<?php

namespace App\Modules\Shared\Services;

use App\Models\User;

class OutboundMessageSignature
{
    /**
     * Add a human agent signature without changing the body stored in the
     * conversation. Drivers use payload.provider_body for the actual delivery,
     * while the inbox renders payload.agent_signature as a separate bold line.
     *
     * @return array{body:?string,payload:array}
     */
    public function apply(
        User $user,
        string $channel,
        ?string $body,
        ?array $payload = null,
        string $type = 'text',
    ): array {
        $payload ??= [];

        // Approved templates and content without text cannot be modified.
        if ($type === 'template' || trim((string) $body) === '') {
            return ['body' => $body, 'payload' => $payload];
        }

        $name = preg_replace('/[\r\n:*_~`<>]+/u', ' ', trim((string) $user->name));
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            $name = 'Atendente';
        }
        $name = mb_substr($name, 0, 80);
        $label = $name.':';

        $providerBody = match ($channel) {
            'whatsapp' => "*{$label}*\n{$body}",
            'telegram' => '<b>'.htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</b>\n"
                .htmlspecialchars((string) $body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            default => "{$label}\n{$body}",
        };

        $payload['agent_signature'] = $label;
        $payload['provider_body'] = $providerBody;
        $payload['provider_body_format'] = $channel === 'telegram' ? 'html' : 'plain';

        if (in_array($type, ['image', 'video', 'document'], true)) {
            $payload['caption'] = $providerBody;
        }

        return ['body' => $body, 'payload' => $payload];
    }
}
