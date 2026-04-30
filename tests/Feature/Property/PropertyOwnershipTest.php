<?php

namespace Tests\Feature\Property;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_sync_ownership_when_total_is_exactly_100_percent(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $ownerOne */
        $ownerOne = User::factory()->create();
        $ownerOne->assignRole('owner');

        $property = Property::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('properties.owners.sync', $property), [
                'owners' => [
                    [
                        'user_id' => $ownerOne->id,
                        'owner_name' => null,
                        'ownership_pct' => 60,
                        'capital_contribution' => 600000,
                        'notes' => 'Primary investor',
                    ],
                    [
                        'user_id' => null,
                        'owner_name' => 'Passive Investor Two',
                        'ownership_pct' => 40,
                        'capital_contribution' => 400000,
                        'notes' => 'External partner',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('property_owners', 2);
        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $property->id,
            'event' => 'property.ownership_updated',
        ]);
    }

    public function test_ownership_sync_rejects_total_not_equal_to_100_percent(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create();

        $this->actingAs($superAdmin)
            ->from(route('properties.show', $property))
            ->post(route('properties.owners.sync', $property), [
                'owners' => [
                    [
                        'owner_name' => 'Owner A',
                        'ownership_pct' => 70,
                        'capital_contribution' => 700000,
                    ],
                    [
                        'owner_name' => 'Owner B',
                        'ownership_pct' => 20,
                        'capital_contribution' => 200000,
                    ],
                ],
            ])
            ->assertSessionHasErrors('owners');

        $this->assertDatabaseCount('property_owners', 0);
    }
}
