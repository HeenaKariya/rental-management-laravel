<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->unsignedInteger('grace_period_days')->default(5)->after('billing_day');
            $table->string('late_fee_mode', 20)->default('fixed')->after('grace_period_days');
            $table->decimal('late_fee_value', 12, 2)->default(0)->after('late_fee_mode');
        });

        Schema::create('rent_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->date('payment_month');
            $table->date('due_on');
            $table->decimal('base_rent_amount', 12, 2)->default(0);
            $table->decimal('carried_arrears', 12, 2)->default(0);
            $table->decimal('credit_brought_forward', 12, 2)->default(0);
            $table->decimal('total_due', 12, 2)->default(0);
            $table->decimal('total_received', 12, 2)->default(0);
            $table->decimal('late_fee_total', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->string('status', 30)->default('unpaid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lease_id', 'payment_month'], 'rent_ledgers_lease_month_unique');
            $table->index(['status', 'due_on'], 'rent_ledgers_status_due_idx');
        });

        Schema::create('rent_instalments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_ledger_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('instalment_number');
            $table->decimal('amount_paid', 12, 2);
            $table->decimal('late_fee_charged', 12, 2)->default(0);
            $table->date('payment_date');
            $table->string('payment_mode', 30);
            $table->string('reference_number')->nullable();
            $table->text('late_fee_waiver_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rent_ledger_id', 'instalment_number'], 'rent_instalments_ledger_number_unique');
            $table->index(['payment_date', 'payment_mode'], 'rent_instalments_date_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_instalments');
        Schema::dropIfExists('rent_ledgers');

        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn(['grace_period_days', 'late_fee_mode', 'late_fee_value']);
        });
    }
};