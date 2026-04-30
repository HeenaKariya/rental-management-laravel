<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_event_settings', function (Blueprint $table): void {
            $table->boolean('email_enabled')->default(true)->after('is_enabled');
            $table->boolean('whatsapp_enabled')->default(false)->after('email_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('notification_event_settings', function (Blueprint $table): void {
            $table->dropColumn(['email_enabled', 'whatsapp_enabled']);
        });
    }
};
