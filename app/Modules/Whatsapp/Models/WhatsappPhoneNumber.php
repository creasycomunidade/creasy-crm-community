<?php

namespace App\Modules\Whatsapp\Models;

use App\Support\Concerns\MasksDemoData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappPhoneNumber extends Model
{
    use MasksDemoData;

    protected $table = 'whatsapp_phone_numbers';

    protected $fillable = [
        'waba_id_fk',
        'phone_number_id',
        'display_phone',
        'verified_name',
        'quality_rating',
        'messaging_limit_tier',
        'code_verification_status',
        'name_status',
        'requested_verified_name',
        'account_mode',
    ];

    /**
     * Hide the connected business number / verified name in demo mode.
     *
     * @return array<string, string>
     */
    protected function demoMask(): array
    {
        return [
            'display_phone' => 'phone',
            'verified_name' => 'name',
            'requested_verified_name' => 'name',
        ];
    }

    public function businessAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'waba_id_fk');
    }
}
