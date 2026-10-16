<?php

namespace Database\Factories;

use App\Modules\AI\Models\AiChatbot;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiChatbotFactory extends Factory
{
    protected $model = AiChatbot::class;

    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'name' => $this->faker->words(2, true).' Bot',
            'system_prompt' => 'You are a helpful assistant.',
            'tone' => 'professional',
            'max_context_chunks' => 5,
            'enabled' => true,
        ];
    }
}
