<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->longText('body_html');
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
        });

        Schema::create('rent_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('agreement_templates')->nullOnDelete();
            $table->longText('generated_content');
            $table->string('token', 120)->unique();
            $table->string('status', 30)->default('generated');
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signing_ip')->nullable();
            $table->string('signing_device')->nullable();
            $table->string('signing_method', 50)->nullable();
            $table->string('signature_label')->nullable();
            $table->string('signed_content_hash', 64)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lease_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_agreements');
        Schema::dropIfExists('agreement_templates');
    }
};