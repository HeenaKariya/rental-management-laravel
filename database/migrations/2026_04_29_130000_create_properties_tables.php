<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type');
            $table->string('street_address');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code', 20);
            $table->string('country');
            $table->decimal('area', 12, 2)->nullable();
            $table->string('area_unit', 12)->default('sqft');
            $table->string('lifecycle_stage')->default('draft');
            $table->timestamp('lifecycle_stage_changed_at')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'lifecycle_stage'], 'properties_type_stage_idx');
        });

        Schema::create('property_manager_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'manager_id', 'revoked_at'], 'prop_mgr_assignments_active_idx');
        });

        Schema::create('property_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['property_id', 'sort_order'], 'property_photos_sort_idx');
        });

        Schema::create('property_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['property_id', 'occurred_at'], 'property_activity_timeline_idx');
            $table->index('event', 'property_activity_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_activity_logs');
        Schema::dropIfExists('property_photos');
        Schema::dropIfExists('property_manager_assignments');
        Schema::dropIfExists('properties');
    }
};
