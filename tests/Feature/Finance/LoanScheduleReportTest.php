<?php

namespace Tests\Feature\Finance;

use App\Models\Property;
use App\Models\PropertyLoan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanScheduleReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_loan_schedule_report_and_export_csv_pdf(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $property = Property::factory()->create(['title' => 'Loan Report Asset']);

        $loan = PropertyLoan::query()->create([
            'property_id' => $property->id,
            'lender_name' => 'Axis Bank',
            'loan_amount' => 1000000,
            'interest_rate' => 8.5,
            'interest_rate_type' => 'floating',
            'loan_start_date' => '2026-01-01',
            'tenure_months' => 120,
            'emi_amount' => 12000,
            'emi_due_day' => 5,
        ]);

        $loan->emiLogs()->create([
            'emi_number' => 1,
            'amount_paid' => 12000,
            'date_paid' => '2026-04-10',
            'principal_component' => 8000,
            'interest_component' => 4000,
            'outstanding_balance' => 992000,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.loan-schedule.index'))
            ->assertOk()
            ->assertSee('Loan schedule report')
            ->assertSee('Loan Report Asset')
            ->assertSee('12,000.00');

        $query = [
            'property_id' => $property->id,
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ];

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.loan-schedule.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($superAdmin)
            ->get(route('finance.reports.loan-schedule.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_manager_only_sees_assigned_property_loan_rows(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $visibleProperty = Property::factory()->create(['title' => 'Visible Loan Property']);
        $hiddenProperty = Property::factory()->create(['title' => 'Hidden Loan Property']);

        $visibleProperty->assignManager($manager, $superAdmin);

        $visibleLoan = PropertyLoan::query()->create([
            'property_id' => $visibleProperty->id,
            'lender_name' => 'Visible Lender',
            'loan_amount' => 500000,
            'interest_rate' => 8,
            'interest_rate_type' => 'fixed',
            'loan_start_date' => '2026-01-01',
            'tenure_months' => 60,
            'emi_amount' => 9000,
            'emi_due_day' => 7,
        ]);

        $hiddenLoan = PropertyLoan::query()->create([
            'property_id' => $hiddenProperty->id,
            'lender_name' => 'Hidden Lender',
            'loan_amount' => 700000,
            'interest_rate' => 9,
            'interest_rate_type' => 'floating',
            'loan_start_date' => '2026-01-01',
            'tenure_months' => 84,
            'emi_amount' => 11000,
            'emi_due_day' => 9,
        ]);

        $visibleLoan->emiLogs()->create([
            'emi_number' => 1,
            'amount_paid' => 9000,
            'date_paid' => '2026-04-12',
            'principal_component' => 6000,
            'interest_component' => 3000,
            'outstanding_balance' => 494000,
        ]);

        $hiddenLoan->emiLogs()->create([
            'emi_number' => 1,
            'amount_paid' => 11000,
            'date_paid' => '2026-04-13',
            'principal_component' => 7000,
            'interest_component' => 4000,
            'outstanding_balance' => 693000,
        ]);

        $this->actingAs($manager)
            ->get(route('finance.reports.loan-schedule.index'))
            ->assertOk()
            ->assertSee('Visible Loan Property')
            ->assertDontSee('Hidden Loan Property')
            ->assertSee('Visible Lender')
            ->assertDontSee('Hidden Lender');
    }
}
