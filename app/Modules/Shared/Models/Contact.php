<?php

namespace App\Modules\Shared\Models;

use App\Services\StorageManager;
use App\Support\Concerns\MasksDemoData;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property string|null $phone_e164
 * @property string|null $email
 * @property string|null $website_url
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $avatar
 * @property string|null $country
 * @property string|null $language
 * @property bool $opt_in_whatsapp
 * @property bool $opt_in_sms
 * @property bool $opt_in_email
 * @property array<string, mixed>|null $custom_fields
 * @property array<string, string>|null $social_profiles
 * @property array<int, array{label: string, url: string}>|null $contact_links
 * @property Carbon|null $last_seen_at
 * @property string|null $source
 * @property int|null $lead_id
 * @property-read string $full_name
 * @property-read string|null $avatar_url
 */
class Contact extends Model
{
    use HasFactory, MasksDemoData, SoftDeletes;

    protected static function newFactory()
    {
        return ContactFactory::new();
    }

    /**
     * Contact PII masked in demo mode (see App\Support\Concerns\MasksDemoData).
     *
     * @return array<string, string>
     */
    protected function demoMask(): array
    {
        return [
            'phone_e164' => 'phone',
            'email' => 'email',
            'first_name' => 'name',
            'last_name' => 'name',
            'full_name' => 'name',
            'custom_fields' => 'array',
            'social_profiles' => 'array',
            'contact_links' => 'array',
            'website_url' => 'null',
            'avatar' => 'null',
            'avatar_url' => 'null',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $appends = ['full_name', 'avatar_url'];

    protected $fillable = [
        'workspace_id', 'phone_e164', 'email', 'website_url', 'first_name', 'last_name',
        'avatar', 'country', 'language', 'opt_in_whatsapp', 'opt_in_sms', 'opt_in_email',
        'custom_fields', 'social_profiles', 'contact_links', 'last_seen_at', 'source', 'lead_id',
    ];

    protected function casts(): array
    {
        return [
            'opt_in_whatsapp' => 'boolean',
            'opt_in_sms' => 'boolean',
            'opt_in_email' => 'boolean',
            'custom_fields' => 'array',
            'social_profiles' => 'array',
            'contact_links' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContactTag::class, 'contact_tag_pivot', 'contact_id', 'tag_id');
    }

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class, 'segment_contact');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }
        // External URLs (from WhatsApp/Instagram/Messenger) returned as-is
        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        return app(StorageManager::class)->disk()->url($this->avatar);
    }
}
