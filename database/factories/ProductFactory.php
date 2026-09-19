<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'slug' => fn (array $attributes) => Str::slug($attributes['name']),
            'short_description' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(3),
            'return_policy' => $this->faker->paragraph(3),
            'color' => $this->faker->randomElement([json_encode(['red', 'blue', 'green']), json_encode(['yellow', 'black', 'white']), json_encode(['orange', 'purple', 'brown'])]),
            'sizes' => $this->faker->randomElement([json_encode(['S', 'M', 'L', 'XL', 'XXL']), json_encode(['S', 'M', 'L', 'XL', 'XXL']), json_encode(['S', 'M', 'L', 'XL', 'XXL'])]),
            'compere_price' => $this->faker->randomFloat(3, 100, 200),
            'price' => $this->faker->randomFloat(3, 10, 100),
            'stock' => $this->faker->numberBetween(10, 100),
            'sku' => $this->faker->name,
            'status' => $this->faker->randomElement([0, 1]),
            'featured' => $this->faker->randomElement([0, 1]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
