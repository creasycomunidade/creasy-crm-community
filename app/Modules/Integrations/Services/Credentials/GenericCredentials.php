<?php

namespace App\Modules\Integrations\Services\Credentials;

class GenericCredentials extends CredentialValueObject
{
    public function apiKey(): ?string
    {
        return $this->get('api_key');
    }

    public function url(): ?string
    {
        return $this->get('url');
    }
}
