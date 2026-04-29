<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    public function definition(): array
    {
        $startOn = now()->subMonths(2)->startOfMonth();
        $endOn = (clone $startOn)->addYear()->subDay();

        return [
            'lease_number' => 'LS-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'unit_id' => Unit::factory(),
            'tenant_id' => Tenant::factory(),
            'previous_lease_id' => null,
            'start_on' => $startOn->toDateString(),
            'end_on' => $endOn->toDateString(),
            'rent_amount' => fake()->randomFloat(2, 8000, 35000),
            'billing_day' => fake()->numberBetween(1, 28),
            'grace_period_days' => fake()->numberBetween(3, 7),
            'late_fee_mode' => 'fixed',
            'late_fee_value' => fake()->randomFloat(2, 250, 1200),
            'status' => 'draft',
            'active_lease_guard' => null,
            'activated_at' => null,
            'terminated_at' => null,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}