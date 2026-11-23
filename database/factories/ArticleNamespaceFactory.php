<?php

namespace Database\Factories;

use App\Models\ArticleNamespace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArticleNamespace> */
class ArticleNamespaceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
        ];
    }
}
