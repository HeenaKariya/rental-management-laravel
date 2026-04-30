<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_owners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_name')->nullable();
            $table->decimal('ownership_pct', 5, 2);
            $table->decimal('capital_contribution', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['property_id', 'is_active'], 'property_owners_active_idx');
        });

        Schema::create('property_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 20);
            $table->date('entry_date');
            $table->string('category', 60);
            $table->decimal('amount', 12, 2);
            $table->string('vendor_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->nullableMorphs('source');
            $table->string('status', 30)->default('approved');
            $table->text('flagged_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['property_id', 'entry_date'], 'property_ledger_entries_property_date_idx');
            $table->index(['entry_type', 'status'], 'property_ledger_entries_type_status_idx');
            $table->unique(['source_type', 'source_id', 'entry_type', 'category'], 'property_ledger_entries_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_ledger_entries');
        Schema::dropIfExists('property_owners');
    }
};