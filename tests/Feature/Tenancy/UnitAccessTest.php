<?php

namespace Tests\Feature\Tenancy;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_unit_for_a_property(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('units.store'), $this->unitPayload([
                'property_id' => $property->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('units', [
            'property_id' => $property->id,
            'unit_number' => 'A-101',
        ]);
    }

    public function test_manager_only_sees_units_for_assigned_properties_and_cannot_open_unassigned_unit_urls(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $assignedProperty = Property::factory()->create(['title' => 'Assigned Residency']);
        $assignedProperty->assignManager($manager, $manager);
        $assignedUnit = Unit::factory()->create([
            'property_id' => $assignedProperty->id,
            'unit_number' => 'B-201',
        ]);

        $hiddenProperty = Property::factory()->create(['title' => 'Hidden Residency']);
        $hiddenUnit = Unit::factory()->create([
            'property_id' => $hiddenProperty->id,
            'unit_number' => 'C-301',
        ]);

        $this->actingAs($manager)
            ->get(route('units.index'))
            ->assertOk()
            ->assertSee('B-201')
            ->assertDontSee('C-301');

        $this->actingAs($manager)
            ->get(route('units.show', $assignedUnit))
            ->assertOk()
            ->assertSee('B-201');

        $this->actingAs($manager)
            ->get(route('units.show', $hiddenUnit))
            ->assertForbidden();
    }

    public function test_manager_cannot_create_a_unit_for_an_unassigned_property(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $property = Property::factory()->create();

        $this->actingAs($manager)
            ->post(route('units.store'), $this->unitPayload([
                'property_id' => $property->id,
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('units', 0);
    }

    public function test_unit_number_must_be_unique_within_a_property(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create();

        Unit::factory()->create([
            'property_id' => $property->id,
            'unit_number' => 'D-401',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('units.store'), $this->unitPayload([
                'property_id' => $property->id,
                'unit_number' => 'D-401',
            ]))
            ->assertSessionHasErrors('unit_number');
    }

    public function test_manager_can_update_an_assigned_unit(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $property = Property::factory()->create();
        $property->assignManager($manager, $manager);

        $unit = Unit::factory()->create([
            'property_id' => $property->id,
            'unit_number' => 'E-501',
            'occupancy_status' => 'vacant',
        ]);

        $this->actingAs($manager)
            ->put(route('units.update', $unit), $this->unitPayload([
                'property_id' => $property->id,
                'unit_number' => 'E-501',
                'occupancy_status' => 'occupied',
                'vacant_since' => null,
            ]))
            ->assertRedirect(route('units.show', $unit));

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'occupancy_status' => 'occupied',
        ]);
    }

    private function unitPayload(array $overrides = []): array
    {
        return [
            'property_id' => Property::factory()->create()->id,
            'unit_number' => 'A-101',
            'floor' => '10',
            'bedrooms' => 2,
            'bathrooms' => 2,
            'area' => '1200',
            'area_unit' => 'sqft',
            'occupancy_status' => 'vacant',
            'vacant_since' => now()->toDateString(),
            'notes' => 'Initial unit seed.',
            ...$overrides,
        ];
    }
}