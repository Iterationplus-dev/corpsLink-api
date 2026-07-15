<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => $this->faker->randomElement(['Documents', 'Camp Essentials']),
            'label' => $this->faker->words(3, true),
            'sort_order' => 0,
        ];
    }
}
