<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_agreements', function (Blueprint $table): void {
            $table->string('signed_pdf_path')->nullable()->after('signature_label');
            $table->string('signed_pdf_hash', 64)->nullable()->after('signed_pdf_path');
            $table->timestamp('integrity_last_checked_at')->nullable()->after('signed_pdf_hash');
            $table->string('integrity_check_status', 20)->nullable()->after('integrity_last_checked_at');
            $table->foreignId('integrity_checked_by')->nullable()->after('integrity_check_status')->constrained('users')->nullOnDelete();
            $table->text('integrity_check_notes')->nullable()->after('integrity_checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('rent_agreements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('integrity_checked_by');
            $table->dropColumn([
                'signed_pdf_path',
                'signed_pdf_hash',
                'integrity_last_checked_at',
                'integrity_check_status',
                'integrity_check_notes',
            ]);
        });
    }
};