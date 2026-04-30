<?php

namespace Tests\Feature\Finance;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_reports_and_download_csv_and_pdf_exports(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $property = Property::factory()->create(['title' => 'Report Property']);
        $property->owners()->create([
            'user_id' => $owner->id,
            'ownership_pct' => 100,
            'capital_contribution' => 1000000,
            'is_active' => true,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $property->ledgerEntries()->create([
            'entry_type' => 'income',
            'entry_date' => '2026-04-15',
            'category' => 'rent_payment',
            'amount' => 10000,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $property->ledgerEntries()->create([
            'entry_type' => 'expense',
            'entry_date' => '2026-04-16',
            'category' => 'maintenance',
            'amount' => 2500,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.show', $property))
            ->assertOk()
            ->assertSee('Owner statement and report matrix')
            ->assertSee('Report Property')
            ->assertSee('Current period: All time');

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.owner-statement.csv', $property))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.owner-statement.pdf', $property))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.pnl-matrix.csv', $property))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.pnl-matrix.pdf', $property))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_owner_cannot_access_reports_for_unowned_property(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $hiddenProperty = Property::factory()->create();

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.show', $hiddenProperty))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.owner-statement.csv', $hiddenProperty))
            ->assertForbidden();
    }

    public function test_reports_respect_custom_date_range_for_operational_totals(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $property = Property::factory()->create();
        $property->owners()->create([
            'user_id' => $owner->id,
            'ownership_pct' => 100,
            'capital_contribution' => 500000,
            'is_active' => true,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $property->ledgerEntries()->create([
            'entry_type' => 'income',
            'entry_date' => '2026-03-12',
            'category' => 'rent_payment',
            'amount' => 4000,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $property->ledgerEntries()->create([
            'entry_type' => 'income',
            'entry_date' => '2026-04-15',
            'category' => 'rent_payment',
            'amount' => 10000,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $property->ledgerEntries()->create([
            'entry_type' => 'expense',
            'entry_date' => '2026-04-16',
            'category' => 'maintenance',
            'amount' => 2500,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.show', [
                $property,
                'period' => 'custom',
                'date_from' => '2026-04-01',
                'date_to' => '2026-04-30',
            ]))
            ->assertOk()
            ->assertSee('Current period: 2026-04-01 to 2026-04-30')
            ->assertSee('10,000.00')
            ->assertDontSee('14,000.00');
    }

    public function test_reports_support_ytd_quick_filter_totals(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $owner */
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $property = Property::factory()->create();
        $property->owners()->create([
            'user_id' => $owner->id,
            'ownership_pct' => 100,
            'capital_contribution' => 500000,
            'is_active' => true,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $property->ledgerEntries()->create([
            'entry_type' => 'income',
            'entry_date' => '2025-12-20',
            'category' => 'rent_payment',
            'amount' => 3000,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
        $property->ledgerEntries()->create([
            'entry_type' => 'income',
            'entry_date' => '2026-02-15',
            'category' => 'rent_payment',
            'amount' => 9000,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($owner)
            ->get(route('properties.finance.reports.show', [
                $property,
                'period' => 'ytd',
            ]))
            ->assertOk()
            ->assertSee('Year to date')
            ->assertSee('9,000.00')
            ->assertDontSee('12,000.00');
    }
}