<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'mediable_type' => User::class,
            'mediable_id'   => User::factory(),
            'disk'          => 'public',
            'path'          => 'media/'.$this->faker->uuid().'.jpg',
            'filename'      => $this->faker->word().'.jpg',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => $this->faker->numberBetween(10000, 5000000),
            'collection'    => 'default',
        ];
    }
}
