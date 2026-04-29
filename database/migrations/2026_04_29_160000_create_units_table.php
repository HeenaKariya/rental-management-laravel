<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('unit_number', 80);
            $table->string('floor', 40)->nullable();
            $table->unsignedInteger('bedrooms')->nullable();
            $table->decimal('bathrooms', 4, 1)->nullable();
            $table->decimal('area', 12, 2)->nullable();
            $table->string('area_unit', 12)->default('sqft');
            $table->string('occupancy_status', 40)->default('vacant');
            $table->date('vacant_since')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'unit_number'], 'units_property_unit_unique');
            $table->index(['property_id', 'occupancy_status'], 'units_property_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};