<?php

namespace Database\Factories;

use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    protected $model = MaintenanceRequest::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'tenant_id' => Tenant::factory(),
            'submitted_by' => User::factory(),
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(MaintenanceRequest::CATEGORIES),
            'priority' => fake()->randomElement(MaintenanceRequest::PRIORITIES),
            'description' => fake()->paragraph(),
            'status' => 'open',
            'internal_notes' => null,
            'vendor_name' => null,
            'repair_cost' => null,
            'resolved_at' => null,
            'updated_by' => null,
        ];
    }
}
