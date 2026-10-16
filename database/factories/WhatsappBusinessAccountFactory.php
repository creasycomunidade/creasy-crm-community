<?php

namespace Database\Factories;

use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsappBusinessAccountFactory extends Factory
{
    protected $model = WhatsappBusinessAccount::class;

    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'waba_id' => $this->faker->numerify('##############'),
            'webhook_verify_token' => 'test-verify-token',
            'status' => 'active',
            'credentials' => ['system_user_token' => 'test-token', 'phone_number_id' => '12345'],
        ];
    }
}
