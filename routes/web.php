<?php

use App\Http\Controllers\Admin\TwoFactorOversightController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Settings\SecuritySettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return $request->user()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'full-auth-session'])
    ->name('dashboard');

Route::middleware(['auth', 'full-auth-session'])->group(function () {
    Route::get('/settings/security', [SecuritySettingsController::class, 'show'])->name('settings.security');

    Route::middleware('password.confirm')->group(function () {
        Route::post('/settings/security/two-factor', [SecuritySettingsController::class, 'enableTwoFactor'])
            ->name('settings.security.two-factor.enable');
        Route::post('/settings/security/two-factor/confirm', [SecuritySettingsController::class, 'confirmTwoFactor'])
            ->name('settings.security.two-factor.confirm');
        Route::delete('/settings/security/two-factor', [SecuritySettingsController::class, 'disableTwoFactor'])
            ->name('settings.security.two-factor.disable');
        Route::post('/settings/security/two-factor/recovery-codes', [SecuritySettingsController::class, 'regenerateRecoveryCodes'])
            ->name('settings.security.two-factor.recovery-codes');
    });
});

Route::middleware(['auth', 'full-auth-session', 'role:super_admin'])->group(function () {
    Route::get('/admin/security/two-factor', [TwoFactorOversightController::class, 'index'])
        ->name('admin.security.two-factor.index');
    Route::post('/admin/security/two-factor/{user}/release-lock', [TwoFactorOversightController::class, 'releaseLock'])
        ->name('admin.security.two-factor.release-lock');
    Route::post('/admin/security/two-factor/{user}/reset', [TwoFactorOversightController::class, 'resetTwoFactor'])
        ->name('admin.security.two-factor.reset');
    Route::get('/admin/invitations/create', [InvitationController::class, 'create'])->name('invitations.create');
    Route::post('/admin/invitations', [InvitationController::class, 'store'])->name('invitations.store');
});

Route::get('/invite/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
