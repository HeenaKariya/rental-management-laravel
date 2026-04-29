<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'user_id' => null,
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'status' => fake()->randomElement(Tenant::STATUSES),
            'kyc_status' => fake()->randomElement(Tenant::KYC_STATUSES),
            'move_in_on' => now()->subDays(fake()->numberBetween(1, 120))->toDateString(),
            'move_out_on' => null,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}