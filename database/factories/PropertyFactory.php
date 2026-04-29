<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $title = fake()->streetName().' Residency';

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(10, 9999),
            'type' => fake()->randomElement(Property::TYPES),
            'street_address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'India',
            'area' => fake()->randomFloat(2, 450, 12000),
            'area_unit' => fake()->randomElement(['sqft', 'sqm']),
            'lifecycle_stage' => fake()->randomElement(Property::LIFECYCLE_STAGES),
            'lifecycle_stage_changed_at' => now(),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
