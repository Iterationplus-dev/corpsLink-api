<?php

namespace Database\Factories;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' University';

        return [
            'name' => $name,
            'abbreviation' => strtoupper(fake()->unique()->lexify('???')),
            'type' => fake()->randomElement(InstitutionType::cases()),
            'state' => fake()->state(),
            'logo_path' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
