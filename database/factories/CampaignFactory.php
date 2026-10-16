<?php

namespace Database\Factories;

use App\Modules\Broadcasting\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'name' => $this->faker->sentence(3),
            'channel' => $this->faker->randomElement(['sms', 'email', 'whatsapp']),
            'payload_json' => ['body' => $this->faker->paragraph()],
            'status' => 'draft',
        ];
    }
}
