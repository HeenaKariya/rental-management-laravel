<?php

namespace Tests\Feature\Property;

use App\Domain\Property\Notifications\PropertyManagerAssignmentNotification;
use App\Models\Property;
use App\Models\PropertyManagerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PropertyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_super_admin_can_create_a_property_and_assign_a_manager(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($superAdmin)
            ->post(route('properties.store'), $this->propertyPayload([
                'assigned_manager_id' => $manager->id,
            ]))
            ->assertRedirect();

        $property = Property::query()->firstOrFail();

        $this->assertTrue($property->isManagedBy($manager));
        Notification::assertSentTo($manager, PropertyManagerAssignmentNotification::class, function (PropertyManagerAssignmentNotification $notification) use ($property) {
            $mailMessage = $notification->toMail(new User());

            return $mailMessage->subject === 'You were assigned to a property in PropMgr'
                && collect($mailMessage->introLines)->contains('You now have manager access to "'.$property->title.'".');
        });
        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $property->id,
            'event' => 'property.manager_assigned',
            'subject_user_id' => $manager->id,
        ]);
    }

    public function test_manager_only_sees_assigned_properties_and_cannot_open_unassigned_property_urls(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $assignedProperty = Property::factory()->create([
            'title' => 'Assigned Tower',
        ]);
        $assignedProperty->assignManager($manager, $manager);

        $unassignedProperty = Property::factory()->create([
            'title' => 'Hidden Plaza',
        ]);

        $this->actingAs($manager)
            ->get(route('properties.index'))
            ->assertOk()
            ->assertSee('Assigned Tower')
            ->assertDontSee('Hidden Plaza');

        $this->actingAs($manager)
            ->get(route('properties.show', $assignedProperty))
            ->assertOk()
            ->assertSee('Assigned Tower');

        $this->actingAs($manager)
            ->get(route('properties.show', $unassignedProperty))
            ->assertForbidden();
    }

    public function test_manager_created_property_is_auto_assigned_back_to_the_creator(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->post(route('properties.store'), $this->propertyPayload([
                'title' => 'Manager Seeded Property',
            ]))
            ->assertRedirect();

        $property = Property::query()->where('title', 'Manager Seeded Property')->firstOrFail();

        $this->assertTrue($property->isManagedBy($manager));
    }

    public function test_super_admin_can_revoke_assignment_and_manager_loses_visibility(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $property = Property::factory()->create();
        $assignment = $property->assignManager($manager, $superAdmin);

        $this->actingAs($superAdmin)
            ->delete(route('properties.assignments.destroy', [$property, $assignment]))
            ->assertRedirect();

        $this->assertNotNull($assignment->fresh()->revoked_at);
        Notification::assertSentTo($manager, PropertyManagerAssignmentNotification::class, function (PropertyManagerAssignmentNotification $notification) use ($property) {
            $mailMessage = $notification->toMail(new User());

            return $mailMessage->subject === 'Your PropMgr property assignment was removed'
                && collect($mailMessage->introLines)->contains('Your manager access to "'.$property->title.'" has been removed.');
        });
        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $property->id,
            'event' => 'property.manager_revoked',
            'subject_user_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('properties.show', $property))
            ->assertForbidden();
    }

    public function test_super_admin_can_reassign_manager_from_the_edit_form(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $firstManager */
        $firstManager = User::factory()->create();
        $firstManager->assignRole('manager');

        /** @var User $secondManager */
        $secondManager = User::factory()->create();
        $secondManager->assignRole('manager');

        $property = Property::factory()->create([
            'title' => 'Reassignment Tower',
        ]);
        $property->assignManager($firstManager, $superAdmin);

        $this->actingAs($superAdmin)
            ->put(route('properties.update', $property), $this->propertyPayload([
                'title' => 'Reassignment Tower',
                'assigned_manager_id' => $secondManager->id,
            ]))
            ->assertRedirect(route('properties.show', $property));

        $this->assertTrue($property->fresh()->isManagedBy($secondManager));
        $this->assertFalse($property->fresh()->isManagedBy($firstManager));

        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $property->id,
            'event' => 'property.manager_assigned',
            'subject_user_id' => $secondManager->id,
        ]);

        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $property->id,
            'event' => 'property.manager_revoked',
            'subject_user_id' => $firstManager->id,
        ]);

        $this->actingAs($firstManager)
            ->get(route('properties.show', $property))
            ->assertForbidden();

        $this->actingAs($secondManager)
            ->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('Reassignment Tower');
    }

    public function test_super_admin_can_soft_archive_a_property(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create([
            'title' => 'Archive Target',
        ]);

        $this->actingAs($superAdmin)
            ->delete(route('properties.archive', $property))
            ->assertRedirect(route('properties.index'));

        $this->assertSoftDeleted('properties', [
            'id' => $property->id,
        ]);

        $this->assertDatabaseHas('property_activity_logs', [
            'property_id' => $property->id,
            'event' => 'property.archived',
        ]);
    }

    public function test_super_admin_can_reorder_photos_and_select_a_new_cover(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create();

        $firstPhoto = $property->photos()->create([
            'disk' => 'public',
            'path' => 'properties/test-one.jpg',
            'sort_order' => 1,
            'is_cover' => true,
            'uploaded_by' => $superAdmin->id,
        ]);

        $secondPhoto = $property->photos()->create([
            'disk' => 'public',
            'path' => 'properties/test-two.jpg',
            'sort_order' => 2,
            'is_cover' => false,
            'uploaded_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->put(route('properties.update', $property), $this->propertyPayload([
                'cover_photo_id' => $secondPhoto->id,
                'photo_orders' => [
                    $firstPhoto->id => 2,
                    $secondPhoto->id => 1,
                ],
            ]))
            ->assertRedirect(route('properties.show', $property));

        $orderedPhotos = $property->fresh()->photos()->get();

        $this->assertSame($secondPhoto->id, $orderedPhotos->first()->id);
        $this->assertTrue((bool) $orderedPhotos->first()->is_cover);
        $this->assertFalse((bool) $orderedPhotos->last()->is_cover);
    }

    private function propertyPayload(array $overrides = []): array
    {
        return [
            'title' => 'Sunrise Residency',
            'type' => 'residential',
            'street_address' => 'Plot 18, MG Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'country' => 'India',
            'area' => '1200',
            'area_unit' => 'sqft',
            'lifecycle_stage' => 'draft',
            'description' => 'Initial Phase 2 property seed.',
            ...$overrides,
        ];
    }
}