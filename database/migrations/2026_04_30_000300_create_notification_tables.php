<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key')->unique();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('lead_days')->default(0);
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key');
            $table->nullableMorphs('notifiable');
            $table->string('recipient_email')->nullable();
            $table->string('channel', 40)->default('email');
            $table->string('status', 20)->default('pending');
            $table->string('subject')->nullable();
            $table->string('message_preview', 255)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['event_key', 'status'], 'notification_deliveries_event_status_idx');
            $table->index(['channel', 'status'], 'notification_deliveries_channel_status_idx');
            $table->index('sent_at', 'notification_deliveries_sent_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_event_settings');
    }
};
