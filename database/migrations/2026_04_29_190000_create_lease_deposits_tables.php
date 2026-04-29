<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('expected_amount', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->decimal('collected_total', 12, 2)->default(0);
            $table->decimal('top_up_total', 12, 2)->default(0);
            $table->decimal('deducted_total', 12, 2)->default(0);
            $table->decimal('refunded_total', 12, 2)->default(0);
            $table->decimal('forfeited_total', 12, 2)->default(0);
            $table->string('status', 40)->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'current_balance'], 'lease_deposits_status_balance_idx');
        });

        Schema::create('lease_deposit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_deposit_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 40);
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['lease_deposit_id', 'occurred_at'], 'lease_deposit_entries_timeline_idx');
            $table->index('entry_type', 'lease_deposit_entries_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_deposit_entries');
        Schema::dropIfExists('lease_deposits');
    }
};