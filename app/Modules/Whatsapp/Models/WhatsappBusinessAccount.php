<?php

namespace App\Modules\Whatsapp\Models;

use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Shared\Models\ChannelAccount;
use Database\Factories\WhatsappBusinessAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappBusinessAccount extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return WhatsappBusinessAccountFactory::new();
    }

    protected $table = 'whatsapp_business_accounts';

    protected $fillable = [
        'workspace_id', 'waba_id', 'credentials', 'webhook_verify_token', 'webhook_verify_token_hash', 'status', 'meta_json',
    ];

    protected $hidden = ['credentials', 'webhook_verify_token'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'webhook_verify_token' => 'encrypted',
            'meta_json' => 'array',
        ];
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(WhatsappPhoneNumber::class, 'waba_id_fk');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsappTemplate::class, 'waba_id', 'waba_id');
    }

    public static function hashWebhookToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** O(1) lookup for per-WABA webhook routes (token is stored encrypted). */
    public static function findByWebhookToken(string $token): ?self
    {
        return static::where('webhook_verify_token_hash', static::hashWebhookToken($token))->first();
    }

    /** Access token for Graph API (embedded OAuth or manual system user). */
    public function accessToken(): ?string
    {
        $creds = $this->credentials ?? [];

        return $creds['system_user_token'] ?? $creds['access_token'] ?? null;
    }

    public static function resolveAccessTokenForWorkspace(int $workspaceId): ?string
    {
        $waba = static::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->first();

        if ($waba) {
            $token = $waba->accessToken();
            if ($token) {
                return $token;
            }
        }

        return CredentialResolver::system()->meta()?->systemUserToken();
    }

    public static function defaultPhoneNumberIdForWorkspace(int $workspaceId): ?string
    {
        $fromChannel = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'whatsapp')
            ->where('status', 'active')
            ->whereNotNull('phone_number_id')
            ->orderBy('id')
            ->value('phone_number_id');

        if ($fromChannel) {
            return (string) $fromChannel;
        }

        $waba = static::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->with('phoneNumbers')
            ->first();

        return $waba?->phoneNumbers->first()?->phone_number_id;
    }

    protected static function booted(): void
    {
        static::saving(function (self $waba) {
            if ($waba->isDirty('webhook_verify_token') && $waba->webhook_verify_token) {
                $waba->webhook_verify_token_hash = static::hashWebhookToken($waba->webhook_verify_token);
            }
        });
    }
}
