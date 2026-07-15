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
            // 3 random letters (17,576 combos) used to collide with the real
            // seeded dataset's ~650 short acronyms once both existed in the
            // same database — wider + prefixed keeps fake data unmistakably
            // synthetic and practically collision-proof against real rows.
            'abbreviation' => 'TEST-'.strtoupper(fake()->unique()->lexify('??????')),
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
