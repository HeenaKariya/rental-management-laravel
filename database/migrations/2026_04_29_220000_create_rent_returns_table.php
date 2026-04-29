<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('vacation_date');
            $table->date('last_paid_through_date')->nullable();
            $table->date('billing_month');
            $table->decimal('daily_rate', 10, 4)->default(0);
            $table->unsignedInteger('unused_days')->default(0);
            $table->decimal('suggested_amount', 12, 2)->default(0);
            $table->decimal('confirmed_amount', 12, 2)->nullable();
            $table->text('override_reason')->nullable();
            $table->string('status')->default('initiated');
            $table->string('settlement_method')->nullable();
            $table->decimal('settlement_amount', 12, 2)->nullable();
            $table->date('settlement_date')->nullable();
            $table->string('settlement_reference')->nullable();
            $table->text('settlement_details')->nullable();
            $table->boolean('ledger_posted')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('initiated_at');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_returns');
    }
};