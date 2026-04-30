<?php

namespace Database\Seeders;

use App\Models\Lease;
use App\Models\LeaseDeposit;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoTenancySeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::query()->where('email', 'test@example.com')->firstOrFail();

        $tenantAUser = User::query()->firstOrCreate(
            ['email' => 'tenant.one@example.com'],
            [
                'name' => 'Meera Tenant',
                'password' => 'password',
                'phone' => '+15550000011',
            ]
        );
        $tenantAUser->assignRole('tenant');

        $tenantBUser = User::query()->firstOrCreate(
            ['email' => 'tenant.two@example.com'],
            [
                'name' => 'Kabir Tenant',
                'password' => 'password',
                'phone' => '+15550000012',
            ]
        );
        $tenantBUser->assignRole('tenant');

        $propertyA = Property::query()->where('slug', 'riverfront-residency')->firstOrFail();
        $propertyB = Property::query()->where('slug', 'market-square-arcade')->firstOrFail();
        $propertyC = Property::query()->where('slug', 'northgate-land-parcel')->firstOrFail();

        $units = [
            [
                'property' => $propertyA,
                'unit_number' => 'A-101',
                'floor' => '1',
                'bedrooms' => 2,
                'bathrooms' => 2,
                'area' => 1180,
                'area_unit' => 'sqft',
                'occupancy_status' => 'occupied',
                'vacant_since' => null,
                'notes' => 'Occupied residential unit for active lease demonstrations.',
            ],
            [
                'property' => $propertyA,
                'unit_number' => 'A-102',
                'floor' => '1',
                'bedrooms' => 3,
                'bathrooms' => 2,
                'area' => 1340,
                'area_unit' => 'sqft',
                'occupancy_status' => 'vacant',
                'vacant_since' => now()->subDays(12)->toDateString(),
                'notes' => 'Vacant unit ready for the next leasing cycle.',
            ],
            [
                'property' => $propertyB,
                'unit_number' => 'C-201',
                'floor' => '2',
                'bedrooms' => 0,
                'bathrooms' => 1,
                'area' => 860,
                'area_unit' => 'sqft',
                'occupancy_status' => 'occupied',
                'vacant_since' => null,
                'notes' => 'Commercial unit currently under an active tenancy.',
            ],
            [
                'property' => $propertyB,
                'unit_number' => 'C-202',
                'floor' => '2',
                'bedrooms' => 0,
                'bathrooms' => 1,
                'area' => 910,
                'area_unit' => 'sqft',
                'occupancy_status' => 'reserved',
                'vacant_since' => null,
                'notes' => 'Reserved unit prepared for upcoming tenant onboarding.',
            ],
            [
                'property' => $propertyC,
                'unit_number' => 'L-001',
                'floor' => null,
                'bedrooms' => 0,
                'bathrooms' => 0,
                'area' => 5200,
                'area_unit' => 'sqm',
                'occupancy_status' => 'under_maintenance',
                'vacant_since' => null,
                'notes' => 'Placeholder inventory record for future development planning.',
            ],
        ];

        $persistedUnits = [];

        foreach ($units as $seed) {
            $unit = Unit::query()->firstOrCreate(
                [
                    'property_id' => $seed['property']->id,
                    'unit_number' => $seed['unit_number'],
                ],
                [
                    'floor' => $seed['floor'],
                    'bedrooms' => $seed['bedrooms'],
                    'bathrooms' => $seed['bathrooms'],
                    'area' => $seed['area'],
                    'area_unit' => $seed['area_unit'],
                    'occupancy_status' => $seed['occupancy_status'],
                    'vacant_since' => $seed['vacant_since'],
                    'notes' => $seed['notes'],
                    'created_by' => $superAdmin->id,
                    'updated_by' => $superAdmin->id,
                ]
            );

            $persistedUnits[$seed['unit_number']] = $unit;
        }

        $tenants = [
            [
                'unit' => $persistedUnits['A-101'],
                'user' => $tenantAUser,
                'full_name' => 'Meera Tenant',
                'email' => $tenantAUser->email,
                'phone' => $tenantAUser->phone,
                'status' => 'active',
                'kyc_status' => 'verified',
                'move_in_on' => '2026-01-01',
                'move_out_on' => null,
                'notes' => 'Primary residential tenant with verified KYC and active lease.',
                'documents' => [
                    ['document_type' => 'identity', 'original_name' => 'meera-passport.pdf'],
                    ['document_type' => 'address', 'original_name' => 'meera-address-proof.pdf'],
                ],
            ],
            [
                'unit' => $persistedUnits['C-201'],
                'user' => $tenantBUser,
                'full_name' => 'Kabir Tenant',
                'email' => $tenantBUser->email,
                'phone' => $tenantBUser->phone,
                'status' => 'active',
                'kyc_status' => 'submitted',
                'move_in_on' => '2026-03-15',
                'move_out_on' => null,
                'notes' => 'Commercial tenant with documents submitted and lease active.',
                'documents' => [
                    ['document_type' => 'identity', 'original_name' => 'kabir-id-card.pdf'],
                    ['document_type' => 'income', 'original_name' => 'kabir-business-registration.pdf'],
                ],
            ],
            [
                'unit' => $persistedUnits['C-202'],
                'user' => null,
                'full_name' => 'Reserved Prospect',
                'email' => 'prospect@example.com',
                'phone' => '+15550000013',
                'status' => 'prospect',
                'kyc_status' => 'pending',
                'move_in_on' => null,
                'move_out_on' => null,
                'notes' => 'Prospect tenant record reserved for upcoming lease conversion.',
                'documents' => [],
            ],
        ];

        $persistedTenants = [];

        foreach ($tenants as $seed) {
            $tenant = Tenant::query()->firstOrCreate(
                [
                    'unit_id' => $seed['unit']->id,
                    'full_name' => $seed['full_name'],
                ],
                [
                    'user_id' => $seed['user']?->id,
                    'email' => $seed['email'],
                    'phone' => $seed['phone'],
                    'status' => $seed['status'],
                    'kyc_status' => $seed['kyc_status'],
                    'move_in_on' => $seed['move_in_on'],
                    'move_out_on' => $seed['move_out_on'],
                    'notes' => $seed['notes'],
                    'created_by' => $superAdmin->id,
                    'updated_by' => $superAdmin->id,
                ]
            );

            foreach ($seed['documents'] as $documentSeed) {
                TenantDocument::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'original_name' => $documentSeed['original_name'],
                    ],
                    [
                        'document_type' => $documentSeed['document_type'],
                        'disk' => 'public',
                        'path' => 'demo/tenants/'.$tenant->id.'/'.$documentSeed['original_name'],
                        'mime_type' => 'application/pdf',
                        'file_size' => 128000,
                        'uploaded_by' => $superAdmin->id,
                        'uploaded_at' => now()->subDays(3),
                    ]
                );
            }

            $persistedTenants[$seed['full_name']] = $tenant;
        }

        $leases = [
            [
                'lease_number' => 'LS-2026-RIVER-A101',
                'unit' => $persistedUnits['A-101'],
                'tenant' => $persistedTenants['Meera Tenant'],
                'start_on' => '2026-01-01',
                'end_on' => '2026-12-31',
                'rent_amount' => 18500,
                'billing_day' => 5,
                'status' => 'active',
                'notes' => 'Primary active residential lease used for rent and renewal demos.',
            ],
            [
                'lease_number' => 'LS-2026-MARKET-C201',
                'unit' => $persistedUnits['C-201'],
                'tenant' => $persistedTenants['Kabir Tenant'],
                'start_on' => '2026-03-15',
                'end_on' => '2027-03-14',
                'rent_amount' => 26500,
                'billing_day' => 10,
                'status' => 'active',
                'notes' => 'Active commercial lease with submitted KYC status.',
            ],
            [
                'lease_number' => 'LS-2025-MARKET-C202-DRAFT',
                'unit' => $persistedUnits['C-202'],
                'tenant' => $persistedTenants['Reserved Prospect'],
                'start_on' => '2026-05-01',
                'end_on' => '2027-04-30',
                'rent_amount' => 21000,
                'billing_day' => 1,
                'status' => 'draft',
                'notes' => 'Draft successor lease awaiting final KYC and activation.',
            ],
        ];

        foreach ($leases as $seed) {
            Lease::query()->firstOrCreate(
                ['lease_number' => $seed['lease_number']],
                [
                    'unit_id' => $seed['unit']->id,
                    'tenant_id' => $seed['tenant']->id,
                    'previous_lease_id' => null,
                    'start_on' => $seed['start_on'],
                    'end_on' => $seed['end_on'],
                    'rent_amount' => $seed['rent_amount'],
                    'billing_day' => $seed['billing_day'],
                    'status' => $seed['status'],
                    'notes' => $seed['notes'],
                    'created_by' => $superAdmin->id,
                    'updated_by' => $superAdmin->id,
                ]
            );
        }

        $depositSeeds = [
            [
                'lease_number' => 'LS-2026-RIVER-A101',
                'expected_amount' => 30000,
                'entries' => [
                    ['entry_type' => 'collection', 'amount' => 30000, 'notes' => 'Initial security deposit collection.'],
                    ['entry_type' => 'deduction', 'amount' => 2500, 'notes' => 'Minor repair reserve held from deposit.'],
                ],
            ],
            [
                'lease_number' => 'LS-2026-MARKET-C201',
                'expected_amount' => 45000,
                'entries' => [
                    ['entry_type' => 'collection', 'amount' => 45000, 'notes' => 'Commercial security deposit collection.'],
                    ['entry_type' => 'top_up', 'amount' => 5000, 'notes' => 'Additional top-up for expanded fit-out risk.'],
                ],
            ],
        ];

        foreach ($depositSeeds as $seed) {
            $lease = Lease::query()->where('lease_number', $seed['lease_number'])->first();

            if (! $lease) {
                continue;
            }

            $deposit = LeaseDeposit::query()->firstOrCreate(
                ['lease_id' => $lease->id],
                [
                    'expected_amount' => $seed['expected_amount'],
                    'notes' => 'Demo deposit ledger for seeded tenancy records.',
                    'created_by' => $superAdmin->id,
                    'updated_by' => $superAdmin->id,
                ]
            );

            if ($deposit->entries()->exists()) {
                continue;
            }

            foreach ($seed['entries'] as $entrySeed) {
                $deposit->postEntry($entrySeed['entry_type'], $entrySeed['amount'], $superAdmin, $entrySeed['notes']);
            }
        }
    }
}
