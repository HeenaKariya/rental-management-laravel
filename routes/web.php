<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Property\PropertyController;
use App\Http\Controllers\Property\PropertyManagerAssignmentController;
use App\Http\Controllers\Admin\TwoFactorOversightController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\TwoFactorOtpController;
use App\Http\Controllers\Settings\SecuritySettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return $request->user()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'full-auth-session'])
    ->name('dashboard');

Route::middleware(['auth', 'full-auth-session'])->group(function () {
    Route::get('/settings/security', [SecuritySettingsController::class, 'show'])->name('settings.security');
    Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('/properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
    Route::put('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
    Route::delete('/properties/{property}', [PropertyController::class, 'archive'])->name('properties.archive');
    Route::post('/properties/{property}/assignments', [PropertyManagerAssignmentController::class, 'store'])
        ->name('properties.assignments.store');
    Route::delete('/properties/{property}/assignments/{assignment}', [PropertyManagerAssignmentController::class, 'destroy'])
        ->name('properties.assignments.destroy');

    Route::middleware('password.confirm')->group(function () {
        Route::post('/settings/security/two-factor', [SecuritySettingsController::class, 'enableTwoFactor'])
            ->name('settings.security.two-factor.enable');
        Route::post('/settings/security/two-factor/confirm', [SecuritySettingsController::class, 'confirmTwoFactor'])
            ->name('settings.security.two-factor.confirm');
        Route::delete('/settings/security/two-factor', [SecuritySettingsController::class, 'disableTwoFactor'])
            ->name('settings.security.two-factor.disable');
        Route::post('/settings/security/two-factor/otp/resend', [SecuritySettingsController::class, 'resendOtp'])
            ->name('settings.security.two-factor.otp.resend');
        Route::post('/settings/security/two-factor/recovery-codes', [SecuritySettingsController::class, 'regenerateRecoveryCodes'])
            ->name('settings.security.two-factor.recovery-codes');
    });
});

Route::post('/two-factor-challenge/otp/resend', [TwoFactorOtpController::class, 'resend'])
    ->middleware('guest')
    ->name('two-factor.otp.resend');

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
