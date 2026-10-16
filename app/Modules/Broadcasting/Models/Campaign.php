<?php

namespace App\Modules\Broadcasting\Models;

use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $channel
 * @property int|null $channel_account_id
 * @property string|null $whatsapp_phone_number_id
 * @property string $audience_type
 * @property string|null $audience_ref
 * @property array<string, mixed>|null $template_ref
 * @property array<string, mixed>|null $payload_json
 * @property Carbon|null $schedule_at
 * @property string|null $timezone
 * @property string $status
 * @property array<string, mixed>|null $totals_json
 * @property int|null $created_by
 */
class Campaign extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return CampaignFactory::new();
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

    protected $fillable = [
        'workspace_id', 'name', 'channel', 'channel_account_id', 'whatsapp_phone_number_id', 'audience_type', 'audience_ref',
        'template_ref', 'payload_json', 'schedule_at', 'timezone', 'status', 'totals_json', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'template_ref' => 'array',
            'payload_json' => 'array',
            'totals_json' => 'array',
            'schedule_at' => 'datetime',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function updateTotals(): void
    {
        $counts = $this->recipients()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        // Count clicks independently — a recipient can click without their status
        // changing to a distinct "clicked" value (we keep status = 'read').
        $clicked = $this->recipients()
            ->whereNotNull('clicked_at')
            ->count();

        // Count unsubscribes independently.
        $unsubscribed = $this->recipients()
            ->whereNotNull('opted_out_at')
            ->count();

        $this->update([
            'totals_json' => [
                'total'        => array_sum($counts),
                'queued'       => $counts['queued'] ?? 0,
                'sent'         => $counts['sent'] ?? 0,
                'delivered'    => $counts['delivered'] ?? 0,
                'read'         => $counts['read'] ?? 0,
                'failed'       => $counts['failed'] ?? 0,
                'clicked'      => $clicked,
                'unsubscribed' => $unsubscribed,
            ],
        ]);
    }
}
