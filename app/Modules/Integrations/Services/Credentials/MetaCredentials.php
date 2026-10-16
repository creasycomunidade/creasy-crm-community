<?php

namespace App\Modules\Integrations\Services\Credentials;

class MetaCredentials extends CredentialValueObject
{
    public function appId(): ?string
    {
        return $this->get('app_id');
    }

    public function appSecret(): ?string
    {
        return $this->get('app_secret');
    }

    public function systemUserToken(): ?string
    {
        return $this->get('system_user_token');
    }

    public function verifyToken(): ?string
    {
        return $this->get('verify_token');
    }

    public function configIdWhatsapp(): ?string
    {
        return $this->get('config_id_whatsapp') ?: null;
    }

    public function configIdSocial(): ?string
    {
        return $this->get('config_id_social') ?: null;
    }
}
