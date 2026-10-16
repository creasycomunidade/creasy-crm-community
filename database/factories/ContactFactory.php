<?php

namespace Database\Factories;

use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone_e164' => '+880'.$this->faker->numerify('1#########'),
            'email' => $this->faker->unique()->safeEmail(),
            'opt_in_whatsapp' => true,
            'opt_in_sms' => true,
            'opt_in_email' => true,
            'source' => 'import',
        ];
    }
}
