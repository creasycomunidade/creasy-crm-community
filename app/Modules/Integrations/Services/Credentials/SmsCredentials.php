<?php

namespace App\Modules\Integrations\Services\Credentials;

class SmsCredentials extends CredentialValueObject
{
    public function apiKey(): ?string
    {
        return $this->get('api_key') ?? $this->get('auth_token') ?? $this->get('api_secret');
    }

    public function accountSid(): ?string
    {
        return $this->get('account_sid');
    }

    public function from(): ?string
    {
        return $this->get('from_number') ?? $this->get('from') ?? $this->get('originator') ?? $this->get('sender') ?? $this->get('mask');
    }

    public function apiSecret(): ?string
    {
        return $this->get('api_secret');
    }
}
