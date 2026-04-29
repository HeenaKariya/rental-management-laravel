<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_instalments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('recorded_by');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
        });

        Schema::create('rent_instalment_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_instalment_id')->constrained()->cascadeOnDelete();
            $table->string('field_name', 40);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['rent_instalment_id', 'changed_at'], 'rent_instalment_corrections_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_instalment_corrections');

        Schema::table('rent_instalments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};