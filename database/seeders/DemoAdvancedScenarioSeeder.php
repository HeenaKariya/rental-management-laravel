<?php

namespace Database\Seeders;

use App\Models\AgreementTemplate;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyLedgerEntry;
use App\Models\PropertyLoan;
use App\Models\PropertyLoanEmiLog;
use App\Models\PropertyOwner;
use App\Models\PropertyPurchase;
use App\Models\PropertySale;
use App\Models\PropertySaleLead;
use App\Models\RentAgreement;
use App\Models\RentReturn;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoAdvancedScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::query()->where('email', 'test@example.com')->firstOrFail();

        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $coOwner = User::query()->firstOrCreate(
            ['email' => 'owner.co@example.com'],
            [
                'name' => 'Priya Co-Owner',
                'password' => 'password',
                'phone' => '+15550000021',
            ]
        );
        $coOwner->assignRole('owner');

        $propertyA = Property::query()->where('slug', 'riverfront-residency')->firstOrFail();
        $propertyB = Property::query()->where('slug', 'market-square-arcade')->firstOrFail();
        $propertyC = Property::query()->where('slug', 'northgate-land-parcel')->firstOrFail();

        $this->seedOwnership($superAdmin, $owner, $coOwner, $propertyA, $propertyB);
        $this->seedFinance($superAdmin, $propertyA, $propertyB, $propertyC);
        $this->seedSales($superAdmin, $propertyB, $propertyC);
        $this->seedRentAndReturns($superAdmin, $propertyA);
        $this->seedAgreements($superAdmin);
    }

    private function seedOwnership(User $superAdmin, User $owner, User $coOwner, Property $propertyA, Property $propertyB): void
    {
        PropertyOwner::query()->updateOrCreate(
            [
                'property_id' => $propertyA->id,
                'user_id' => $owner->id,
            ],
            [
                'owner_name' => $owner->name,
                'ownership_pct' => 70,
                'capital_contribution' => 4900000,
                'is_active' => true,
                'notes' => 'Primary ownership share.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        PropertyOwner::query()->updateOrCreate(
            [
                'property_id' => $propertyA->id,
                'user_id' => $coOwner->id,
            ],
            [
                'owner_name' => $coOwner->name,
                'ownership_pct' => 30,
                'capital_contribution' => 2100000,
                'is_active' => true,
                'notes' => 'Joint owner for statement split demos.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        PropertyOwner::query()->updateOrCreate(
            [
                'property_id' => $propertyA->id,
                'owner_name' => 'Legacy Partner',
            ],
            [
                'user_id' => null,
                'ownership_pct' => 20,
                'capital_contribution' => 0,
                'is_active' => false,
                'notes' => 'Historical owner archived from active split calculations.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        PropertyOwner::query()->updateOrCreate(
            [
                'property_id' => $propertyB->id,
                'user_id' => $owner->id,
            ],
            [
                'owner_name' => $owner->name,
                'ownership_pct' => 100,
                'capital_contribution' => 8200000,
                'is_active' => true,
                'notes' => 'Single-owner commercial holding.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );
    }

    private function seedFinance(User $superAdmin, Property $propertyA, Property $propertyB, Property $propertyC): void
    {
        PropertyPurchase::query()->updateOrCreate(
            ['property_id' => $propertyA->id],
            [
                'purchase_price' => 7000000,
                'purchase_date' => '2023-04-15',
                'stamp_duty' => 350000,
                'registration_charges' => 70000,
                'other_acquisition_costs' => 80000,
                'total_acquisition_cost' => 7500000,
                'seller_name' => 'Riverfront Buildcon LLP',
                'seller_contact' => '+91-90000-10001',
                'notes' => 'Residential acquisition for long-term rental yield.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        PropertyPurchase::query()->updateOrCreate(
            ['property_id' => $propertyB->id],
            [
                'purchase_price' => 8200000,
                'purchase_date' => '2022-11-02',
                'stamp_duty' => 410000,
                'registration_charges' => 82000,
                'other_acquisition_costs' => 115000,
                'total_acquisition_cost' => 8807000,
                'seller_name' => 'Square Capital Group',
                'seller_contact' => '+91-90000-10002',
                'notes' => 'Commercial arcade acquisition with anchor tenants.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        PropertyPurchase::query()->updateOrCreate(
            ['property_id' => $propertyC->id],
            [
                'purchase_price' => 4600000,
                'purchase_date' => '2021-08-20',
                'stamp_duty' => 230000,
                'registration_charges' => 50000,
                'other_acquisition_costs' => 45000,
                'total_acquisition_cost' => 4925000,
                'seller_name' => 'Northgate Agri Holdings',
                'seller_contact' => '+91-90000-10003',
                'notes' => 'Land parcel acquired for development and resale.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $loanA = PropertyLoan::query()->updateOrCreate(
            ['property_id' => $propertyA->id],
            [
                'lender_name' => 'Axis Habitat Finance',
                'loan_amount' => 4200000,
                'interest_rate' => 8.45,
                'interest_rate_type' => 'floating',
                'loan_start_date' => '2023-05-01',
                'tenure_months' => 180,
                'emi_amount' => 41250,
                'emi_due_day' => 7,
                'notes' => 'Primary mortgage facility.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $loanB = PropertyLoan::query()->updateOrCreate(
            ['property_id' => $propertyB->id],
            [
                'lender_name' => 'City Merchant Bank',
                'loan_amount' => 5000000,
                'interest_rate' => 9.10,
                'interest_rate_type' => 'fixed',
                'loan_start_date' => '2022-12-01',
                'tenure_months' => 144,
                'emi_amount' => 59875,
                'emi_due_day' => 10,
                'notes' => 'Commercial loan facility for the arcade.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $this->seedEmiLog($loanA, 1, 41250, '2026-01-07', 11450, 29800, 4170200, $superAdmin);
        $this->seedEmiLog($loanA, 2, 41250, '2026-02-07', 11362, 29888, 4140312, $superAdmin);
        $this->seedEmiLog($loanA, 3, 41250, '2026-03-07', 11274, 29976, 4110336, $superAdmin);
        $this->seedEmiLog($loanB, 1, 59875, '2026-01-10', 18050, 41825, 4958175, $superAdmin);

        $this->seedLedgerEntry($propertyA, [
            'entry_type' => 'expense',
            'entry_date' => now()->subDays(16)->toDateString(),
            'category' => 'maintenance',
            'amount' => 18400,
            'vendor_name' => 'Prime Repairs',
            'reference_number' => 'MNT-A101-001',
            'notes' => 'Plumbing and interior maintenance for A-101.',
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->seedLedgerEntry($propertyA, [
            'entry_type' => 'expense',
            'entry_date' => now()->subDays(12)->toDateString(),
            'category' => 'other',
            'amount' => 72000,
            'vendor_name' => 'Rapid Projects',
            'reference_number' => 'OTH-A101-REVIEW',
            'notes' => 'Lobby redesign expense awaiting super admin review.',
            'status' => 'pending_review',
            'flagged_reason' => 'High-value expense entry requires Super Admin review.',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->seedLedgerEntry($propertyB, [
            'entry_type' => 'expense',
            'entry_date' => now()->subDays(8)->toDateString(),
            'category' => 'insurance',
            'amount' => 14300,
            'vendor_name' => 'SecureCover General',
            'reference_number' => 'INS-C201-001',
            'notes' => 'Policy rider mismatch; rejected for correction.',
            'status' => 'rejected',
            'reviewed_by' => $superAdmin->id,
            'reviewed_at' => now()->subDays(7),
            'review_notes' => 'Vendor policy schedule does not match this property.',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->seedLedgerEntry($propertyB, [
            'entry_type' => 'income',
            'entry_date' => now()->subDays(5)->toDateString(),
            'category' => 'other_income',
            'amount' => 9800,
            'vendor_name' => null,
            'reference_number' => 'PARK-C201-APR',
            'notes' => 'Parking and signage income from commercial tenants.',
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->seedLedgerEntry($propertyC, [
            'entry_type' => 'expense',
            'entry_date' => now()->subDays(20)->toDateString(),
            'category' => 'property_tax',
            'amount' => 26500,
            'vendor_name' => 'Municipal Revenue Office',
            'reference_number' => 'TAX-L001-2026',
            'notes' => 'Annual land tax payment.',
            'status' => 'approved',
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);
    }

    private function seedSales(User $superAdmin, Property $propertyB, Property $propertyC): void
    {
        $saleB = PropertySale::query()->updateOrCreate(
            ['property_id' => $propertyB->id],
            [
                'listing_date' => now()->subDays(40)->toDateString(),
                'asking_price' => 12600000,
                'broker_name' => 'Skyline Brokers',
                'broker_contact' => '+91-90000-20001',
                'listing_notes' => 'Actively listed with ongoing investor discussions.',
                'status' => 'for_sale',
                'final_sale_price' => null,
                'sale_date' => null,
                'buyer_name' => null,
                'buyer_contact' => null,
                'sale_deed_path' => null,
                'broker_commission' => 0,
                'closing_costs' => 0,
                'sale_notes' => null,
                'total_acquisition_cost_snapshot' => 8807000,
                'net_sale_proceeds' => null,
                'gross_profit_loss' => null,
                'closed_by' => null,
                'closed_at' => null,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $this->seedSaleLead($saleB, 'Investor One', 'enquiry', now()->subDays(35)->toDateString(), null, null, $superAdmin, 'Initial call and site visit.');
        $this->seedSaleLead($saleB, 'Investor Two', 'offer_made', now()->subDays(24)->toDateString(), 11950000, now()->subDays(23)->toDateString(), $superAdmin, 'Offer shared; awaiting revised term sheet.');
        $this->seedSaleLead($saleB, 'Retail Group Z', 'negotiation', now()->subDays(14)->toDateString(), 12250000, now()->subDays(13)->toDateString(), $superAdmin, 'Negotiating staged payment schedule.');

        $saleC = PropertySale::query()->updateOrCreate(
            ['property_id' => $propertyC->id],
            [
                'listing_date' => now()->subDays(95)->toDateString(),
                'asking_price' => 6200000,
                'broker_name' => 'LandCraft Advisory',
                'broker_contact' => '+91-90000-20002',
                'listing_notes' => 'Land parcel listed for strategic sale.',
                'status' => 'closed',
                'final_sale_price' => 6050000,
                'sale_date' => now()->subDays(18)->toDateString(),
                'buyer_name' => 'Nexa Warehousing Pvt Ltd',
                'buyer_contact' => '+91-90000-20003',
                'sale_deed_path' => 'demo/sales/northgate-sale-deed.pdf',
                'broker_commission' => 121000,
                'closing_costs' => 64000,
                'sale_notes' => 'Sale closed after title clearance.',
                'total_acquisition_cost_snapshot' => 4925000,
                'net_sale_proceeds' => 5865000,
                'gross_profit_loss' => 940000,
                'closed_by' => $superAdmin->id,
                'closed_at' => now()->subDays(18),
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $this->seedSaleLead($saleC, 'Nexa Warehousing Pvt Ltd', 'accepted', now()->subDays(25)->toDateString(), 6050000, now()->subDays(24)->toDateString(), $superAdmin, 'Accepted offer and advanced to closure.');
        $this->seedSaleLead($saleC, 'Land Fund Alpha', 'rejected', now()->subDays(44)->toDateString(), 5600000, now()->subDays(43)->toDateString(), $superAdmin, 'Rejected due to lower valuation.');

        if ($propertyC->lifecycle_stage !== 'sold') {
            $propertyC->forceFill([
                'lifecycle_stage' => 'sold',
                'updated_by' => $superAdmin->id,
            ])->save();
        }
    }

    private function seedRentAndReturns(User $superAdmin, Property $propertyA): void
    {
        $leaseA = Lease::query()->where('lease_number', 'LS-2026-RIVER-A101')->first();
        $leaseB = Lease::query()->where('lease_number', 'LS-2026-MARKET-C201')->first();

        if ($leaseA) {
            $leaseA->ensureRentLedgers($superAdmin);

            $firstLedger = $leaseA->rentLedgers()->whereDate('payment_month', '2026-01-01')->first();
            if ($firstLedger && ! $firstLedger->instalments()->where('reference_number', 'SEED-RIV-001')->exists()) {
                $instalment = $firstLedger->recordInstalment([
                    'amount_paid' => 18500,
                    'late_fee_charged' => 0,
                    'payment_date' => '2026-01-04',
                    'payment_mode' => 'bank_transfer',
                    'reference_number' => 'SEED-RIV-001',
                    'notes' => 'On-time January payment.',
                ], $superAdmin);

                PropertyLedgerEntry::recordRentInstalment($instalment, $superAdmin);
            }

            $secondLedger = $leaseA->rentLedgers()->whereDate('payment_month', '2026-02-01')->first();
            if ($secondLedger && ! $secondLedger->instalments()->where('reference_number', 'SEED-RIV-002')->exists()) {
                $instalment = $secondLedger->recordInstalment([
                    'amount_paid' => 10000,
                    'late_fee_charged' => 350,
                    'payment_date' => '2026-02-14',
                    'payment_mode' => 'upi',
                    'reference_number' => 'SEED-RIV-002',
                    'notes' => 'Partial payment with late fee.',
                ], $superAdmin);

                PropertyLedgerEntry::recordRentInstalment($instalment, $superAdmin);
            }
        }

        if ($leaseB) {
            $leaseB->ensureRentLedgers($superAdmin);

            $firstLedger = $leaseB->rentLedgers()->whereDate('payment_month', '2026-03-01')->first();
            if ($firstLedger && ! $firstLedger->instalments()->where('reference_number', 'SEED-MKT-VOID-001')->exists()) {
                $instalment = $firstLedger->recordInstalment([
                    'amount_paid' => 8500,
                    'late_fee_charged' => 500,
                    'payment_date' => '2026-03-18',
                    'payment_mode' => 'cash',
                    'reference_number' => 'SEED-MKT-VOID-001',
                    'notes' => 'Erroneous cash posting later voided.',
                ], $superAdmin);

                PropertyLedgerEntry::recordRentInstalment($instalment, $superAdmin);
                $instalment->void('Duplicate posting replaced by corrected transfer entry.', $superAdmin);
                PropertyLedgerEntry::recordRentReversal($instalment, $superAdmin);
            }

            $correctedInstalment = $firstLedger?->instalments()->where('reference_number', 'SEED-MKT-001')->first();
            if ($firstLedger && ! $correctedInstalment) {
                $instalment = $firstLedger->recordInstalment([
                    'amount_paid' => 12000,
                    'late_fee_charged' => 0,
                    'payment_date' => '2026-03-20',
                    'payment_mode' => 'bank_transfer',
                    'reference_number' => 'SEED-MKT-001',
                    'notes' => 'Corrected bank transfer instalment.',
                ], $superAdmin);

                PropertyLedgerEntry::recordRentInstalment($instalment, $superAdmin);
            }
        }

        $unitA102 = Unit::query()->where('unit_number', 'A-102')->where('property_id', $propertyA->id)->first();
        if (! $unitA102) {
            return;
        }

        $formerTenant = Tenant::query()->firstOrCreate(
            [
                'unit_id' => $unitA102->id,
                'full_name' => 'Nisha Former',
            ],
            [
                'user_id' => null,
                'email' => 'former.nisha@example.com',
                'phone' => '+15550000014',
                'status' => 'inactive',
                'kyc_status' => 'verified',
                'move_in_on' => '2025-01-01',
                'move_out_on' => '2025-12-20',
                'notes' => 'Historical tenant used for rent return scenarios.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $terminatedLease = Lease::query()->firstOrCreate(
            ['lease_number' => 'LS-2025-RIVER-A102-CLOSED'],
            [
                'unit_id' => $unitA102->id,
                'tenant_id' => $formerTenant->id,
                'previous_lease_id' => null,
                'start_on' => '2025-01-01',
                'end_on' => '2025-12-31',
                'rent_amount' => 16800,
                'billing_day' => 5,
                'grace_period_days' => 5,
                'late_fee_mode' => 'fixed',
                'late_fee_value' => 300,
                'status' => 'terminated',
                'notes' => 'Closed lease retained for return reconciliation demo.',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $terminatedLease->ensureRentLedgers($superAdmin);

        RentReturn::query()->updateOrCreate(
            ['lease_id' => $terminatedLease->id],
            [
                'tenant_id' => $formerTenant->id,
                'unit_id' => $unitA102->id,
                'property_id' => $propertyA->id,
                'vacation_date' => '2025-12-20',
                'last_paid_through_date' => '2025-12-31',
                'billing_month' => '2025-12-01',
                'daily_rate' => 541.9355,
                'unused_days' => 11,
                'suggested_amount' => 5961.29,
                'confirmed_amount' => 6000,
                'override_reason' => 'Rounded up for goodwill settlement.',
                'status' => 'settled',
                'settlement_method' => 'cash_refund',
                'settlement_amount' => 6000,
                'settlement_date' => '2025-12-28',
                'settlement_reference' => 'RR-A102-2025',
                'settlement_details' => 'Returned via bank transfer after final inspection.',
                'ledger_posted' => true,
                'notes' => 'Historical settled rent return.',
                'initiated_by' => $superAdmin->id,
                'initiated_at' => now()->subMonths(4),
                'processed_by' => $superAdmin->id,
                'processed_at' => now()->subMonths(4)->addDay(),
            ]
        );
    }

    private function seedAgreements(User $superAdmin): void
    {
        $templateResidential = AgreementTemplate::query()->updateOrCreate(
            ['name' => 'Residential Standard v1'],
            [
                'body_html' => '<h2>Residential Lease Agreement</h2><p>Tenant: {{tenant_name}}</p><p>Property: {{property_name}} Unit {{unit_number}}</p><p>Term: {{lease_start_date}} to {{lease_end_date}}</p><p>Rent: {{monthly_rent}}</p><p>Deposit: {{security_deposit}}</p>',
                'status' => 'active',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $templateCommercial = AgreementTemplate::query()->updateOrCreate(
            ['name' => 'Commercial Standard v1'],
            [
                'body_html' => '<h2>Commercial Lease Agreement</h2><p>Tenant: {{tenant_name}}</p><p>Property: {{property_name}}</p><p>Monthly rent: {{monthly_rent}}</p>',
                'status' => 'inactive',
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $leaseA = Lease::query()->where('lease_number', 'LS-2026-RIVER-A101')->first();
        $leaseB = Lease::query()->where('lease_number', 'LS-2026-MARKET-C201')->first();
        $leaseDraft = Lease::query()->where('lease_number', 'LS-2025-MARKET-C202-DRAFT')->first();

        if ($leaseA) {
            RentAgreement::query()->updateOrCreate(
                ['token' => 'demo-a101-voided-token'],
                [
                    'lease_id' => $leaseA->id,
                    'tenant_id' => $leaseA->tenant_id,
                    'template_id' => $templateResidential->id,
                    'generated_content' => 'Superseded agreement content for A101.',
                    'status' => 'voided',
                    'voided_at' => now()->subDays(9),
                    'voided_by' => $superAdmin->id,
                    'generated_by' => $superAdmin->id,
                ]
            );

            RentAgreement::query()->updateOrCreate(
                ['token' => 'demo-a101-viewed-token'],
                [
                    'lease_id' => $leaseA->id,
                    'tenant_id' => $leaseA->tenant_id,
                    'template_id' => $templateResidential->id,
                    'generated_content' => 'Viewed but unsigned agreement for A101.',
                    'status' => 'viewed',
                    'first_viewed_at' => now()->subDays(4),
                    'generated_by' => $superAdmin->id,
                ]
            );

            $pdfPath = sprintf('agreements/%d/demo-a101-signed.pdf', $leaseA->id);
            $pdfContent = "%PDF-1.4\n% Demo signed agreement for A101\n";
            Storage::disk('public')->put($pdfPath, $pdfContent);

            RentAgreement::query()->updateOrCreate(
                ['token' => 'demo-a101-signed-token'],
                [
                    'lease_id' => $leaseA->id,
                    'tenant_id' => $leaseA->tenant_id,
                    'template_id' => $templateResidential->id,
                    'generated_content' => 'Signed agreement content for A101.',
                    'status' => 'signed',
                    'first_viewed_at' => now()->subDays(3),
                    'signed_at' => now()->subDays(2),
                    'signing_ip' => '127.0.0.1',
                    'signing_device' => 'Demo Seeder',
                    'signing_method' => 'typed_name',
                    'signature_label' => 'Meera Tenant',
                    'signed_pdf_path' => $pdfPath,
                    'signed_pdf_hash' => hash('sha256', $pdfContent),
                    'signed_content_hash' => hash('sha256', 'a101-signed-content'),
                    'integrity_last_checked_at' => now()->subDay(),
                    'integrity_check_status' => 'verified',
                    'integrity_checked_by' => $superAdmin->id,
                    'integrity_check_notes' => 'Demo verification passed.',
                    'generated_by' => $superAdmin->id,
                ]
            );
        }

        if ($leaseB) {
            $pdfPath = sprintf('agreements/%d/demo-c201-signed.pdf', $leaseB->id);
            $pdfContent = "%PDF-1.4\n% Demo signed agreement for C201\n";
            Storage::disk('public')->put($pdfPath, $pdfContent);

            RentAgreement::query()->updateOrCreate(
                ['token' => 'demo-c201-signed-token'],
                [
                    'lease_id' => $leaseB->id,
                    'tenant_id' => $leaseB->tenant_id,
                    'template_id' => $templateCommercial->id,
                    'generated_content' => 'Signed agreement content for C201.',
                    'status' => 'signed',
                    'first_viewed_at' => now()->subDays(6),
                    'signed_at' => now()->subDays(5),
                    'signing_ip' => '127.0.0.1',
                    'signing_device' => 'Demo Seeder',
                    'signing_method' => 'typed_name',
                    'signature_label' => 'Kabir Tenant',
                    'signed_pdf_path' => $pdfPath,
                    'signed_pdf_hash' => hash('sha256', $pdfContent.'-mismatch'),
                    'signed_content_hash' => hash('sha256', 'c201-signed-content'),
                    'integrity_last_checked_at' => now()->subDays(2),
                    'integrity_check_status' => 'tampered',
                    'integrity_checked_by' => $superAdmin->id,
                    'integrity_check_notes' => 'Demo mismatch retained for integrity failure testing.',
                    'generated_by' => $superAdmin->id,
                ]
            );
        }

        if ($leaseDraft) {
            RentAgreement::query()->updateOrCreate(
                ['token' => 'demo-c202-generated-token'],
                [
                    'lease_id' => $leaseDraft->id,
                    'tenant_id' => $leaseDraft->tenant_id,
                    'template_id' => $templateResidential->id,
                    'generated_content' => 'Draft lease agreement awaiting first view.',
                    'status' => 'generated',
                    'generated_by' => $superAdmin->id,
                ]
            );
        }
    }

    private function seedEmiLog(
        PropertyLoan $loan,
        int $emiNumber,
        float $amountPaid,
        string $datePaid,
        float $interestComponent,
        float $principalComponent,
        float $outstandingBalance,
        User $superAdmin
    ): void {
        PropertyLoanEmiLog::query()->updateOrCreate(
            [
                'property_loan_id' => $loan->id,
                'emi_number' => $emiNumber,
            ],
            [
                'amount_paid' => $amountPaid,
                'date_paid' => $datePaid,
                'principal_component' => $principalComponent,
                'interest_component' => $interestComponent,
                'outstanding_balance' => $outstandingBalance,
                'notes' => 'Demo EMI seed record.',
                'recorded_by' => $superAdmin->id,
            ]
        );
    }

    private function seedLedgerEntry(Property $property, array $attributes): void
    {
        PropertyLedgerEntry::query()->updateOrCreate(
            [
                'property_id' => $property->id,
                'reference_number' => $attributes['reference_number'],
                'entry_type' => $attributes['entry_type'],
                'category' => $attributes['category'],
            ],
            $attributes + ['property_id' => $property->id]
        );
    }

    private function seedSaleLead(
        PropertySale $sale,
        string $buyerName,
        string $status,
        string $inquiryDate,
        ?float $offerAmount,
        ?string $offerDate,
        User $superAdmin,
        string $notes
    ): void {
        PropertySaleLead::query()->updateOrCreate(
            [
                'property_sale_id' => $sale->id,
                'buyer_name' => $buyerName,
                'inquiry_date' => $inquiryDate,
            ],
            [
                'buyer_contact' => '+91-90000-30001',
                'offer_amount' => $offerAmount,
                'offer_date' => $offerDate,
                'status' => $status,
                'notes' => $notes,
                'created_by' => $superAdmin->id,
            ]
        );
    }
}