<?php

namespace Database\Factories;

use App\Models\ArticleImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleImage>
 */
class ArticleImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'path' => hash('sha256', fake()->uuid()).'.png',
            'mime' => 'image/png',
            'size' => fake()->numberBetween(1_000, 500_000),
        ];
    }
}
