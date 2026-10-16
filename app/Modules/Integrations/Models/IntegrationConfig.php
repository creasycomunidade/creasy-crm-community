<?php

namespace App\Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConfig extends Model
{
    // All valid provider slugs
    public const PROVIDERS = [
        'meta_app',
        'evolution_go',
        'waha',
        'evolution_api',
        'llm_openai_default',
        'llm_anthropic_default',
        'llm_gemini_default',
        'qdrant',
        'storage_local',
        'storage_s3',
        'storage_do',
        'storage_wasabi',
    ];

    /** The single provider slug that is the active storage backend. */
    public const STORAGE_PROVIDERS = ['storage_local', 'storage_s3', 'storage_do', 'storage_wasabi'];

    /** Maps provider slug → Laravel disk name. */
    public const STORAGE_DISK_MAP = [
        'storage_local' => 'public',
        'storage_s3' => 's3',
        'storage_do' => 'do_spaces',
        'storage_wasabi' => 'wasabi',
    ];

    // Human-readable labels per provider
    public const LABELS = [
        'meta_app' => 'Meta App (WhatsApp / Instagram / Messenger / Facebook)',
        'evolution_go' => 'Evolution Go (QR Code)',
        'waha' => 'WAHA (WhatsApp)',
        'evolution_api' => 'Evolution API v2 (WhatsApp)',
        'oauth_linkedin' => 'LinkedIn (OAuth)',
        'oauth_twitter' => 'X / Twitter (OAuth)',
        'oauth_youtube' => 'YouTube / Google (OAuth)',
        'oauth_tiktok' => 'TikTok (OAuth)',
        'oauth_shopify' => 'Aplicativo Shopify (OAuth)',
        'oauth_bigcommerce' => 'Aplicativo BigCommerce (OAuth)',
        'llm_openai_default' => 'OpenAI (padrão)',
        'llm_anthropic_default' => 'Anthropic Claude (padrão)',
        'llm_gemini_default' => 'Google Gemini (padrão)',
        'google_places' => 'Google Places API',
        'firecrawl' => 'Firecrawl',
        'zernio' => 'Zernio (conta central multiempresa)',
        'google_workspace' => 'Google Workspace (Planilhas / Documentos / Agenda / Meet)',
        'qdrant' => 'Banco vetorial Qdrant',
        'storage_local' => 'Armazenamento local (disco do servidor)',
        'storage_s3' => 'Amazon S3',
        'storage_do' => 'DigitalOcean Spaces',
        'storage_wasabi' => 'Armazenamento Wasabi Cloud',
    ];

    // Which category each provider belongs to (for UI grouping)
    public const CATEGORIES = [
        'meta_app' => 'Meta',
        'evolution_go' => 'WhatsApp',
        'waha' => 'WhatsApp',
        'evolution_api' => 'WhatsApp',
        'oauth_linkedin' => 'Redes sociais',
        'oauth_twitter' => 'Redes sociais',
        'oauth_youtube' => 'Redes sociais',
        'oauth_tiktok' => 'Redes sociais',
        'oauth_shopify' => 'E-commerce',
        'oauth_bigcommerce' => 'E-commerce',
        'llm_openai_default' => 'IA e modelos de linguagem',
        'llm_anthropic_default' => 'IA e modelos de linguagem',
        'llm_gemini_default' => 'IA e modelos de linguagem',
        'google_places' => 'Mapas',
        'firecrawl' => 'Dados e prospecção',
        'zernio' => 'Canais, redes sociais e anúncios',
        'google_workspace' => 'Google Workspace',
        'qdrant' => 'Banco vetorial',
        'storage_local' => 'Armazenamento',
        'storage_s3' => 'Armazenamento',
        'storage_do' => 'Armazenamento',
        'storage_wasabi' => 'Armazenamento',
    ];

    // Field definitions per provider (used to build dynamic forms)
    public const FIELDS = [
        'meta_app' => [
            ['key' => 'app_id',              'label' => 'ID do aplicativo',                     'type' => 'text',     'required' => true],
            ['key' => 'app_secret',          'label' => 'Chave secreta do aplicativo',          'type' => 'password', 'required' => true],
            ['key' => 'system_user_token',   'label' => 'Token do usuário do sistema',          'type' => 'password', 'required' => false],
            ['key' => 'verify_token',        'label' => 'Token de verificação do webhook',      'type' => 'text',     'required' => false],
            ['key' => 'config_id_whatsapp',  'label' => 'ID do cadastro incorporado (WhatsApp)', 'type' => 'text',     'required' => false, 'hint' => 'No painel do Meta App: Facebook Login for Business → configuração do cadastro incorporado do WhatsApp.'],
            ['key' => 'config_id_social',    'label' => 'ID do cadastro incorporado (Instagram/Messenger)', 'type' => 'text', 'required' => false, 'hint' => 'No painel do Meta App: Facebook Login for Business → configuração do cadastro incorporado social.'],
        ],
        'waha' => [
            ['key' => 'base_url', 'label' => 'URL pública do WAHA', 'type' => 'text', 'required' => true],
            ['key' => 'api_key', 'label' => 'WAHA_API_KEY', 'type' => 'password', 'required' => true],
        ],
        'evolution_api' => [
            ['key' => 'base_url', 'label' => 'URL pública da Evolution API v2', 'type' => 'text', 'required' => true],
            ['key' => 'api_key', 'label' => 'AUTHENTICATION_API_KEY', 'type' => 'password', 'required' => true],
        ],
        'evolution_go' => [
            ['key' => 'base_url', 'label' => 'URL pública do gateway', 'type' => 'text', 'required' => true, 'hint' => 'Use uma URL HTTPS dedicada, sem /swagger no final.'],
            ['key' => 'global_api_key', 'label' => 'Chave global da API', 'type' => 'password', 'required' => true, 'hint' => 'Chave administrativa do gateway. Nunca é exposta aos clientes.'],
        ],
        'oauth_linkedin' => [
            ['key' => 'client_id',     'label' => 'ID do cliente',     'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Chave secreta do cliente', 'type' => 'password', 'required' => true],
        ],
        'oauth_twitter' => [
            ['key' => 'client_id',     'label' => 'ID do cliente',     'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Chave secreta do cliente', 'type' => 'password', 'required' => true],
        ],
        'oauth_youtube' => [
            ['key' => 'client_id',     'label' => 'ID do cliente',     'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Chave secreta do cliente', 'type' => 'password', 'required' => true],
        ],
        'oauth_tiktok' => [
            ['key' => 'client_key',    'label' => 'Chave do cliente',    'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Chave secreta do cliente', 'type' => 'password', 'required' => true],
        ],
        'oauth_shopify' => [
            ['key' => 'client_id',     'label' => 'Chave da API (ID do cliente)',        'type' => 'text',     'required' => true,  'hint' => 'No aplicativo Shopify Partner → Credenciais do cliente.'],
            ['key' => 'client_secret', 'label' => 'Chave secreta da API', 'type' => 'password', 'required' => true],
        ],
        'oauth_bigcommerce' => [
            ['key' => 'client_id',     'label' => 'ID do cliente',     'type' => 'text',     'required' => true,  'hint' => 'Disponível no aplicativo criado no portal de desenvolvedores do BigCommerce.'],
            ['key' => 'client_secret', 'label' => 'Chave secreta do cliente', 'type' => 'password', 'required' => true],
        ],
        'llm_openai_default' => [
            ['key' => 'api_key',        'label' => 'Chave da API',        'type' => 'password', 'required' => true],
            ['key' => 'organization_id', 'label' => 'ID da organização', 'type' => 'text',     'required' => false],
        ],
        'llm_anthropic_default' => [
            ['key' => 'api_key', 'label' => 'Chave da API', 'type' => 'password', 'required' => true],
        ],
        'llm_gemini_default' => [
            ['key' => 'api_key', 'label' => 'Chave da API', 'type' => 'password', 'required' => true],
        ],
        'google_places' => [
            ['key' => 'api_key', 'label' => 'Chave da API', 'type' => 'password', 'required' => true],
        ],
        'firecrawl' => [
            ['key' => 'api_key', 'label' => 'Chave da API Firecrawl', 'type' => 'password', 'required' => true, 'hint' => 'Copie a chave fc-... no painel do Firecrawl. Ela será armazenada criptografada e nunca será enviada ao navegador dos clientes.'],
            ['key' => 'base_url', 'label' => 'URL da API', 'type' => 'text', 'required' => false, 'hint' => 'Use https://api.firecrawl.dev para o serviço oficial. Altere somente se utilizar uma instalação própria compatível com a API v2.'],
        ],
        'zernio' => [
            ['key' => 'api_key', 'label' => 'Chave mestra da API', 'type' => 'password', 'required' => true, 'hint' => 'Chave única da conta Zernio do CREASY CRM. Ela fica criptografada e nunca é enviada ao navegador das empresas.'],
            ['key' => 'base_url', 'label' => 'URL da API', 'type' => 'text', 'required' => false, 'hint' => 'Padrão: https://zernio.com/api/v1'],
        ],
        'google_workspace' => [
            ['key' => 'client_id',     'label' => 'ID do cliente OAuth', 'type' => 'text', 'required' => true, 'hint' => 'Google Cloud Console → APIs e serviços → Credenciais → ID do cliente OAuth → Aplicativo da Web.'],
            ['key' => 'client_secret', 'label' => 'Chave secreta do cliente OAuth', 'type' => 'password', 'required' => true, 'hint' => 'Copie a chave secreta do mesmo cliente OAuth criado no Google Cloud Console.'],
            ['key' => 'refresh_token', 'label' => 'Token de atualização global (opcional)', 'type' => 'password', 'required' => false, 'hint' => 'Não é necessário para a conexão do Google Drive por espaço. Use apenas se recursos antigos do Google Workspace ainda dependerem de uma conta global do sistema.'],
        ],
        'qdrant' => [
            ['key' => 'url',     'label' => 'URL do Qdrant',   'type' => 'text',     'required' => true],
            ['key' => 'api_key', 'label' => 'Chave da API',       'type' => 'password', 'required' => false],
        ],

        'storage_local' => [
            // No credentials required — uses server disk
        ],

        'storage_s3' => [
            ['key' => 'key',                    'label' => 'ID da chave de acesso',          'type' => 'text',     'required' => true],
            ['key' => 'secret',                 'label' => 'Chave de acesso secreta',      'type' => 'password', 'required' => true],
            ['key' => 'region',                 'label' => 'Região',                 'type' => 'text',     'required' => true],
            ['key' => 'bucket',                 'label' => 'Nome do bucket',            'type' => 'text',     'required' => true],
            ['key' => 'url',                    'label' => 'URL personalizada (opcional)',   'type' => 'text',     'required' => false],
            ['key' => 'directory_prefix',       'label' => 'Prefixo do diretório',       'type' => 'text',     'required' => false],
        ],

        'storage_do' => [
            ['key' => 'key',                    'label' => 'Chave de acesso do Spaces',      'type' => 'text',     'required' => true],
            ['key' => 'secret',                 'label' => 'Chave secreta do Spaces',      'type' => 'password', 'required' => true],
            ['key' => 'region',                 'label' => 'Região (ex.: nyc3)',     'type' => 'text',     'required' => true],
            ['key' => 'bucket',                 'label' => 'Nome do Space (bucket)',    'type' => 'text',     'required' => true],
            ['key' => 'endpoint',               'label' => 'URL do endpoint',           'type' => 'text',     'required' => true],
            ['key' => 'url',                    'label' => 'CDN / URL personalizada',       'type' => 'text',     'required' => false],
            ['key' => 'directory_prefix',       'label' => 'Prefixo do diretório',       'type' => 'text',     'required' => false],
        ],

        'storage_wasabi' => [
            ['key' => 'key',                    'label' => 'ID da chave de acesso',          'type' => 'text',     'required' => true],
            ['key' => 'secret',                 'label' => 'Chave de acesso secreta',      'type' => 'password', 'required' => true],
            ['key' => 'region',                 'label' => 'Região (ex.: us-east-1)', 'type' => 'text',     'required' => true],
            ['key' => 'bucket',                 'label' => 'Nome do bucket',            'type' => 'text',     'required' => true],
            ['key' => 'endpoint',               'label' => 'URL do endpoint',           'type' => 'text',     'required' => true],
            ['key' => 'url',                    'label' => 'URL personalizada (opcional)',   'type' => 'text',     'required' => false],
            ['key' => 'directory_prefix',       'label' => 'Prefixo do diretório',       'type' => 'text',     'required' => false],
        ],
    ];

    protected $fillable = [
        'provider',
        'label',
        'mode',
        'enabled',
        'is_default',
        'credentials',
        'webhook_secret',
        'meta_json',
        'updated_by_admin_id',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $hidden = ['credentials', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'meta_json' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    public static function forProvider(string $provider, string $mode = 'live'): ?self
    {
        return static::where('provider', $provider)->where('mode', $mode)->first();
    }

    public function isConfigured(): bool
    {
        // Local storage needs no credentials — it is always considered configured
        if ($this->provider === 'storage_local') {
            return true;
        }

        $creds = $this->credentials ?? [];

        return ! empty($creds) && collect($creds)->filter()->isNotEmpty();
    }

    /** Returns a fixed-length masked preview — never reveals actual credential content. */
    public function maskedCredentials(): array
    {
        $creds = $this->credentials ?? [];
        $result = [];
        foreach ($creds as $k => $v) {
            $result[$k] = (string) $v === '' ? '' : '••••••••••••';
        }

        return $result;
    }
}
