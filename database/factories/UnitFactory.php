<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $status = fake()->randomElement(Unit::OCCUPANCY_STATUSES);

        return [
            'property_id' => Property::factory(),
            'unit_number' => strtoupper(fake()->bothify('A-##?')),
            'floor' => (string) fake()->numberBetween(1, 20),
            'bedrooms' => fake()->numberBetween(1, 4),
            'bathrooms' => fake()->randomElement([1, 1.5, 2, 2.5]),
            'area' => fake()->randomFloat(2, 250, 2500),
            'area_unit' => fake()->randomElement(['sqft', 'sqm']),
            'occupancy_status' => $status,
            'vacant_since' => $status === 'vacant' ? now()->subDays(fake()->numberBetween(1, 45))->toDateString() : null,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}