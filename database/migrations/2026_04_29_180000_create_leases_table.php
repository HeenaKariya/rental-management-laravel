<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->string('lease_number')->unique();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('previous_lease_id')->nullable()->constrained('leases')->nullOnDelete();
            $table->date('start_on');
            $table->date('end_on');
            $table->decimal('rent_amount', 12, 2);
            $table->unsignedTinyInteger('billing_day')->default(1);
            $table->string('status', 40)->default('draft');
            $table->unsignedTinyInteger('active_lease_guard')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'status'], 'leases_unit_status_idx');
            $table->index(['tenant_id', 'status'], 'leases_tenant_status_idx');
            $table->unique(['unit_id', 'active_lease_guard'], 'leases_single_active_per_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};