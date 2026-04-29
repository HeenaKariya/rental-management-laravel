<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('login_failed_attempts')->default(0)->after('two_factor_confirmed_at');
            $table->unsignedSmallInteger('two_factor_failed_attempts')->default(0)->after('login_failed_attempts');
            $table->unsignedSmallInteger('auth_soft_lock_count')->default(0)->after('two_factor_failed_attempts');
            $table->timestamp('auth_soft_locked_until')->nullable()->after('auth_soft_lock_count');
            $table->timestamp('auth_hard_locked_at')->nullable()->after('auth_soft_locked_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'login_failed_attempts',
                'two_factor_failed_attempts',
                'auth_soft_lock_count',
                'auth_soft_locked_until',
                'auth_hard_locked_at',
            ]);
        });
    }
};