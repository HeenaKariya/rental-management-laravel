<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaseDeposit>
 */
class LeaseDepositFactory extends Factory
{
    protected $model = LeaseDeposit::class;

    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'expected_amount' => fake()->randomFloat(2, 10000, 60000),
            'current_balance' => 0,
            'collected_total' => 0,
            'top_up_total' => 0,
            'deducted_total' => 0,
            'refunded_total' => 0,
            'forfeited_total' => 0,
            'status' => 'open',
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}