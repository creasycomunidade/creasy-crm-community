<?php

namespace Database\Factories;

use App\Modules\Broadcasting\Models\CampaignRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    public function definition(): array
    {
        return [
            'campaign_id' => 1,
            'contact_id' => 1,
            'status' => 'pending',
        ];
    }
}
