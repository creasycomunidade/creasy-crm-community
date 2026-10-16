<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url'     => $this->faker->url(),
            'secret'  => 'whsec_'.bin2hex(random_bytes(16)),
            'events'   => ['subscription.created', 'subscription.cancelled'],
            'enabled'  => true,
        ];
    }
}
