<?php

namespace Tests\Feature\Finance;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_filter_expense_report_and_export_csv_pdf(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create(['title' => 'Expense Report Property']);
        $property->ledgerEntries()->create([
            'entry_type' => 'expense',
            'entry_date' => '2026-04-10',
            'category' => 'maintenance',
            'amount' => 25000,
            'status' => 'approved',
            'vendor_name' => 'FixFast',
            'reference_number' => 'EXP-OK-1',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $hiddenProperty = Property::factory()->create(['title' => 'Hidden Expense Property']);
        $hiddenProperty->ledgerEntries()->create([
            'entry_type' => 'expense',
            'entry_date' => '2026-04-11',
            'category' => 'utility',
            'amount' => 5000,
            'status' => 'pending_review',
            'vendor_name' => 'Power Co',
            'reference_number' => 'EXP-HIDDEN-1',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $query = [
            'property_id' => $property->id,
            'status' => 'approved',
            'category' => 'maintenance',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ];

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.expenses.index', $query))
            ->assertOk()
            ->assertSee('Expenses report')
            ->assertSee('Expense Report Property')
            ->assertSee('EXP-OK-1')
            ->assertDontSee('EXP-HIDDEN-1');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.expenses.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.expenses.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_manager_only_sees_expense_rows_for_assigned_property(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $visibleProperty = Property::factory()->create(['title' => 'Visible Expense Property']);
        $hiddenProperty = Property::factory()->create(['title' => 'Hidden Expense Property']);
        $visibleProperty->assignManager($manager, $superAdmin);

        $visibleProperty->ledgerEntries()->create([
            'entry_type' => 'expense',
            'entry_date' => '2026-04-12',
            'category' => 'maintenance',
            'amount' => 12000,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $hiddenProperty->ledgerEntries()->create([
            'entry_type' => 'expense',
            'entry_date' => '2026-04-12',
            'category' => 'utility',
            'amount' => 9000,
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($manager)
            ->get(route('finance.reports.expenses.index'))
            ->assertOk()
            ->assertSee('Visible Expense Property')
            ->assertDontSee('Hidden Expense Property');
    }
}
