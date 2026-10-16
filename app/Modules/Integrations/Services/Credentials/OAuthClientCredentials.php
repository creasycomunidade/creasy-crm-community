<?php

namespace App\Modules\Integrations\Services\Credentials;

class OAuthClientCredentials extends CredentialValueObject
{
    public function clientId(): ?string
    {
        return $this->get('client_id') ?? $this->get('client_key');
    }

    public function clientSecret(): ?string
    {
        return $this->get('client_secret');
    }
}
