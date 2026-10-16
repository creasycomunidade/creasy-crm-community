<?php

namespace Database\Factories;

use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CmsPageFactory extends Factory
{
    protected $model = CmsPage::class;

    public function definition(): array
    {
        return [
            'slug'             => $this->faker->unique()->slug(2),
            'title'            => $this->faker->sentence(3),
            'content'          => '<p>'.$this->faker->paragraph().'</p>',
            'meta_title'       => null,
            'meta_description' => null,
            'published'        => true,
            'layout'           => 'marketing',
        ];
    }

    public function draft(): static
    {
        return $this->state(['published' => false]);
    }
}
