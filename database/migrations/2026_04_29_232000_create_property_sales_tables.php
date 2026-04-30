<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete()->unique();
            $table->date('listing_date')->nullable();
            $table->decimal('asking_price', 12, 2)->default(0);
            $table->string('broker_name')->nullable();
            $table->string('broker_contact')->nullable();
            $table->text('listing_notes')->nullable();
            $table->string('status', 30)->default('for_sale');
            $table->decimal('final_sale_price', 12, 2)->nullable();
            $table->date('sale_date')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_contact')->nullable();
            $table->string('sale_deed_path')->nullable();
            $table->decimal('broker_commission', 12, 2)->default(0);
            $table->decimal('closing_costs', 12, 2)->default(0);
            $table->text('sale_notes')->nullable();
            $table->decimal('total_acquisition_cost_snapshot', 12, 2)->default(0);
            $table->decimal('net_sale_proceeds', 12, 2)->nullable();
            $table->decimal('gross_profit_loss', 12, 2)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('property_sale_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_sale_id')->constrained()->cascadeOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_contact')->nullable();
            $table->date('inquiry_date');
            $table->decimal('offer_amount', 12, 2)->nullable();
            $table->date('offer_date')->nullable();
            $table->string('status', 30)->default('enquiry');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['property_sale_id', 'inquiry_date'], 'property_sale_leads_sale_inquiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_sale_leads');
        Schema::dropIfExists('property_sales');
    }
};