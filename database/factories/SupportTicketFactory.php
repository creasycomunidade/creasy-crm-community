<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'name'     => $this->faker->name(),
            'email'    => $this->faker->safeEmail(),
            'subject'  => $this->faker->sentence(4),
            'message'  => $this->faker->paragraph(),
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'status'   => 'open',
        ];
    }
}
