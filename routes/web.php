<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\LeasePaymentHistoryController;
use App\Http\Controllers\Property\PropertyController;
use App\Http\Controllers\Property\PropertyManagerAssignmentController;
use App\Http\Controllers\Tenancy\LeaseDepositController;
use App\Http\Controllers\Tenancy\LeaseController;
use App\Http\Controllers\Tenancy\TenantController;
use App\Http\Controllers\Tenancy\UnitController;
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
    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::get('/units/{unit}', [UnitController::class, 'show'])->name('units.show');
    Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::get('/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
    Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    Route::get('/leases', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('/leases/create', [LeaseController::class, 'create'])->name('leases.create');
    Route::post('/leases', [LeaseController::class, 'store'])->name('leases.store');
    Route::get('/leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');
    Route::get('/leases/{lease}/payments', [LeasePaymentHistoryController::class, 'show'])->name('leases.payments.show');
    Route::post('/leases/{lease}/payments/{ledger}/instalments', [LeasePaymentHistoryController::class, 'storeInstalment'])->name('leases.payments.instalments.store');
    Route::get('/leases/{lease}/payments/{ledger}/instalments/{instalment}/receipt', [LeasePaymentHistoryController::class, 'downloadReceipt'])->name('leases.payments.receipt.download');
    Route::get('/leases/{lease}/edit', [LeaseController::class, 'edit'])->name('leases.edit');
    Route::put('/leases/{lease}', [LeaseController::class, 'update'])->name('leases.update');
    Route::post('/leases/{lease}/renew', [LeaseController::class, 'renew'])->name('leases.renew');
    Route::get('/deposits', [LeaseDepositController::class, 'index'])->name('deposits.index');
    Route::get('/deposits/create', [LeaseDepositController::class, 'create'])->name('deposits.create');
    Route::post('/deposits', [LeaseDepositController::class, 'store'])->name('deposits.store');
    Route::get('/deposits/{deposit}', [LeaseDepositController::class, 'show'])->name('deposits.show');
    Route::post('/deposits/{deposit}/entries', [LeaseDepositController::class, 'postEntry'])->name('deposits.entries.store');
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
