<?php

namespace Database\Seeders;

use App\Models\AuthAuditLog;
use App\Models\Invitation;
use App\Models\Property;
use App\Models\PropertyActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoPropertySeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ]
        );
        $superAdmin->assignRole('super_admin');

        $managerA = User::query()->firstOrCreate(
            ['email' => 'manager.one@example.com'],
            [
                'name' => 'Aarav Manager',
                'password' => 'password',
                'phone' => '+15550000001',
            ]
        );
        $managerA->assignRole('manager');

        $managerB = User::query()->firstOrCreate(
            ['email' => 'manager.two@example.com'],
            [
                'name' => 'Riya Manager',
                'password' => 'password',
                'phone' => '+15550000002',
            ]
        );
        $managerB->assignRole('manager');

        $owner = User::query()->firstOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Owner Demo',
                'password' => 'password',
            ]
        );
        $owner->assignRole('owner');

        $managerRoleId = Role::query()->where('slug', 'manager')->value('id');

        if ($managerRoleId) {
            Invitation::query()->firstOrCreate(
                ['email' => 'new.manager@example.com'],
                [
                    'role_id' => $managerRoleId,
                    'invited_by' => $superAdmin->id,
                    'token' => (string) str()->uuid(),
                    'expires_at' => now()->addDays(7),
                ]
            );
        }

        $properties = [
            [
                'title' => 'Riverfront Residency',
                'type' => 'residential',
                'street_address' => 'Plot 22, Riverfront Road',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'postal_code' => '380009',
                'country' => 'India',
                'area' => 1820,
                'area_unit' => 'sqft',
                'lifecycle_stage' => 'active',
                'description' => 'Premium river-facing residential block with active occupancy and a finalized manager assignment.',
                'manager' => $managerA,
            ],
            [
                'title' => 'Market Square Arcade',
                'type' => 'commercial',
                'street_address' => '18 Business Street',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'postal_code' => '395003',
                'country' => 'India',
                'area' => 2460,
                'area_unit' => 'sqft',
                'lifecycle_stage' => 'stabilized',
                'description' => 'Mixed retail arcade prepared for the upcoming tenancy and ledger phases.',
                'manager' => $managerB,
            ],
            [
                'title' => 'Northgate Land Parcel',
                'type' => 'land',
                'street_address' => 'Survey 11, Northgate Highway',
                'city' => 'Vadodara',
                'state' => 'Gujarat',
                'postal_code' => '390001',
                'country' => 'India',
                'area' => 7500,
                'area_unit' => 'sqm',
                'lifecycle_stage' => 'draft',
                'description' => 'Draft property entry reserved for future acquisition and owner economics workflows.',
                'manager' => $managerA,
            ],
        ];

        foreach ($properties as $index => $seed) {
            $property = Property::query()->firstOrCreate(
                ['slug' => str($seed['title'])->slug()->toString()],
                [
                    'title' => $seed['title'],
                    'type' => $seed['type'],
                    'street_address' => $seed['street_address'],
                    'city' => $seed['city'],
                    'state' => $seed['state'],
                    'postal_code' => $seed['postal_code'],
                    'country' => $seed['country'],
                    'area' => $seed['area'],
                    'area_unit' => $seed['area_unit'],
                    'lifecycle_stage' => $seed['lifecycle_stage'],
                    'lifecycle_stage_changed_at' => now()->subDays(5 - $index),
                    'description' => $seed['description'],
                    'created_by' => $superAdmin->id,
                    'updated_by' => $superAdmin->id,
                ]
            );

            $property->assignManager($seed['manager'], $superAdmin);

            if (! $property->photos()->exists()) {
                $photoOne = $property->photos()->create([
                    'disk' => 'public',
                    'path' => 'demo/properties/'.str($property->slug)->append('-cover.jpg')->toString(),
                    'caption' => 'Cover shot',
                    'sort_order' => 1,
                    'is_cover' => true,
                    'uploaded_by' => $superAdmin->id,
                ]);

                $property->photos()->create([
                    'disk' => 'public',
                    'path' => 'demo/properties/'.str($property->slug)->append('-gallery.jpg')->toString(),
                    'caption' => 'Gallery shot',
                    'sort_order' => 2,
                    'is_cover' => false,
                    'uploaded_by' => $superAdmin->id,
                ]);

                $property->refreshCoverPhoto($photoOne->id);
            }

            if (! $property->activityLogs()->exists()) {
                PropertyActivityLog::record($property, 'property.created', $superAdmin);
                PropertyActivityLog::record($property, 'property.lifecycle_changed', $superAdmin, [
                    'from' => 'draft',
                    'to' => $property->lifecycle_stage,
                ]);
                PropertyActivityLog::record($property, 'property.updated', $superAdmin);
            }
        }

        if (! AuthAuditLog::query()->exists()) {
            AuthAuditLog::record($superAdmin, 'two_factor.confirmed');
            AuthAuditLog::record($managerA, 'two_factor.otp_sent', ['channel' => 'email']);
            AuthAuditLog::record($managerB, 'two_factor.otp_fallback', ['to' => 'email']);
            AuthAuditLog::record($owner, 'auth.login_failed');
        }
    }
}
