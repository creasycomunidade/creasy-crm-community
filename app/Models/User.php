<?php

namespace App\Models;

use App\Services\ClientWorkspaceService;
use App\Services\Mail\MailService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if (! $user->client_id) {
                return;
            }
            if (! $user->wasRecentlyCreated && ! $user->wasChanged('client_id') && ! $user->wasChanged('client_role')) {
                return;
            }
            app(ClientWorkspaceService::class)->syncClientUser($user);
        });
    }

    public const CLIENT_ROLE_ADMINISTRATOR = 'administrator';

    public const CLIENT_ROLE_STAFF = 'staff';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CLIENT = 'client';

    protected $fillable = [
        'name',
        'email',
        'phone_e164',
        'avatar',
        'password',
        'role',
        'client_id',
        'status',
        'client_role',
        'email_verified_at',
        'locale',
        'display_currency',
        'workspace_id',
        'theme',
        'timezone',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Resolve the stored avatar path to a public URL (null when unset).
     * External URLs are returned as-is; relative paths resolve via the
     * active storage disk.
     */
    public function avatarUrl(): ?string
    {
        if (empty($this->avatar)) {
            return null;
        }
        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        return app(\App\Services\StorageManager::class)->disk()->url($this->avatar);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    // -------------------------------------------------------------------------
    // 2FA
    // -------------------------------------------------------------------------

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    // -------------------------------------------------------------------------
    // Social accounts
    // -------------------------------------------------------------------------

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    // -------------------------------------------------------------------------
    // Client / Organisation
    // -------------------------------------------------------------------------

    /** Client (organisation) this user belongs to (null = standalone user). */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // -------------------------------------------------------------------------
    // Workspaces
    // -------------------------------------------------------------------------

    /** Primary/active workspace for this user. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /** Workspaces this user owns. */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /** Workspaces this user is a member of (via workspace_user pivot). */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** All workspaces the user can access (owned + member). */
    public function accessibleWorkspaces(): Collection
    {
        $owned = $this->ownedWorkspaces()->get();
        $member = $this->workspaces()->get();

        return $owned->merge($member)->unique('id');
    }

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where(function (Builder $memberQuery) use ($workspaceId) {
            $memberQuery
                ->whereHas('workspaces', fn (Builder $q) => $q->where('workspaces.id', $workspaceId))
                ->orWhereHas('ownedWorkspaces', fn (Builder $q) => $q->where('workspaces.id', $workspaceId));
        });
    }

    // -------------------------------------------------------------------------
    // Subscriptions
    // -------------------------------------------------------------------------



    /** Compatibility for shared integrations: Community has no subscription. */
    public function effectiveSubscription(): null
    {
        return null;
    }

    // -------------------------------------------------------------------------
    // Role / status helpers
    // -------------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
    }

    public function isClientUser(): bool
    {
        return $this->role === self::ROLE_CLIENT && $this->client_id !== null;
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function isClientAdministrator(): bool
    {
        return $this->client_role === self::CLIENT_ROLE_ADMINISTRATOR;
    }

    // -------------------------------------------------------------------------
    // Mail overrides (use configured SMTP + templates when available)
    // -------------------------------------------------------------------------

    public function sendPasswordResetNotification($token): void
    {
        $mailService = app(MailService::class);
        $url = url(route('password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ], false));
        $appName = config('app.name');
        $sent = $mailService->sendWithTemplate('reset_password', $this->getEmailForPasswordReset(), [
            'app_name' => $appName,
            'reset_url' => $url,
        ]);
        if (! $sent && ! SmtpConfiguration::isConfigured()) {
            $this->notify(new ResetPassword($token));
        } elseif (! $sent) {
            Log::error('Não foi possível enviar o e-mail de redefinição de senha.', ['user_id' => $this->id]);
        }
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->sendEmailVerificationNow();
    }

    public function sendEmailVerificationNow(): bool
    {
        $mailService = app(MailService::class);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );
        $appName = config('app.name');
        $sent = $mailService->sendWithTemplate('email_verification', $this->getEmailForVerification(), [
            'app_name' => $appName,
            'verification_url' => $url,
        ]);
        if (! $sent && ! SmtpConfiguration::isConfigured()) {
            $this->notify(new VerifyEmail);
            return true;
        }
        if (! $sent) {
            Log::error('Não foi possível enviar o e-mail de confirmação.', ['user_id' => $this->id]);
        }

        return $sent;
    }

    // -------------------------------------------------------------------------
    // Notifications & Webhooks
    // -------------------------------------------------------------------------

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }


    /**
     * Check if a user wants to receive a given notification event on a given channel.
     */
    public function wantsNotification(string $event, string $channel = 'database'): bool
    {
        $pref = $this->notificationPreferences()
            ->where('event', $event)
            ->where('channel', $channel)
            ->first();

        // Default: enabled unless explicitly opted out
        return $pref === null ? true : $pref->enabled;
    }
}
