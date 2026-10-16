<?php

namespace Database\Factories;

use App\Modules\Leads\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'name' => $this->faker->company(),
            'phone' => '+880'.$this->faker->numerify('1#########'),
            'email' => $this->faker->companyEmail(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'country' => 'BD',
            'category' => $this->faker->word(),
            'rating' => $this->faker->randomFloat(1, 1, 5),
            'whatsapp_status' => 'unknown',
            'pushed_to_contacts' => false,
        ];
    }
}
