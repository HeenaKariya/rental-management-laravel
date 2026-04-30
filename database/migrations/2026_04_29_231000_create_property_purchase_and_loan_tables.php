<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete()->unique();
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->date('purchase_date')->nullable();
            $table->decimal('stamp_duty', 12, 2)->default(0);
            $table->decimal('registration_charges', 12, 2)->default(0);
            $table->decimal('other_acquisition_costs', 12, 2)->default(0);
            $table->decimal('total_acquisition_cost', 12, 2)->default(0);
            $table->string('seller_name')->nullable();
            $table->string('seller_contact')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('property_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('lender_name');
            $table->decimal('loan_amount', 12, 2);
            $table->decimal('interest_rate', 6, 3)->default(0);
            $table->string('interest_rate_type', 20)->default('fixed');
            $table->date('loan_start_date');
            $table->unsignedInteger('tenure_months');
            $table->decimal('emi_amount', 12, 2);
            $table->unsignedTinyInteger('emi_due_day');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('property_loan_emi_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('emi_number');
            $table->decimal('amount_paid', 12, 2);
            $table->date('date_paid');
            $table->decimal('principal_component', 12, 2)->default(0);
            $table->decimal('interest_component', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_loan_id', 'emi_number'], 'property_loan_emi_number_unique');
            $table->index(['date_paid'], 'property_loan_emi_date_paid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_loan_emi_logs');
        Schema::dropIfExists('property_loans');
        Schema::dropIfExists('property_purchases');
    }
};