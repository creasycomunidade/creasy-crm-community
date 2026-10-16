<?php

namespace Database\Factories;

use App\Modules\AI\Models\AiKnowledgeBase;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiKnowledgeBaseFactory extends Factory
{
    protected $model = AiKnowledgeBase::class;

    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'name' => $this->faker->words(3, true),
            'embedding_model' => 'text-embedding-3-small',
            'dimensions' => 1536,
            'status' => 'active',
        ];
    }
}
