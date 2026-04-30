<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category', 40);
            $table->string('priority', 20)->default('medium');
            $table->longText('description');
            $table->string('status', 40)->default('open');
            $table->text('internal_notes')->nullable();
            $table->string('vendor_name')->nullable();
            $table->decimal('repair_cost', 12, 2)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'priority'], 'maintenance_requests_status_priority_idx');
            $table->index(['unit_id', 'status'], 'maintenance_requests_unit_status_idx');
        });

        Schema::create('maintenance_request_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_photos');
        Schema::dropIfExists('maintenance_requests');
    }
};
