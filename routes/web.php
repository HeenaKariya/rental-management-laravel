<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Agreement\AgreementTemplateController;
use App\Http\Controllers\Agreement\LeaseAgreementController;
use App\Http\Controllers\Agreement\PublicAgreementSigningController;
use App\Http\Controllers\Finance\RentDashboardController;
use App\Http\Controllers\Finance\PropertyLedgerController;
use App\Http\Controllers\Finance\PropertyPurchaseController;
use App\Http\Controllers\Finance\PropertyReportController;
use App\Http\Controllers\Finance\PropertySaleController;
use App\Http\Controllers\Finance\LeasePaymentHistoryController;
use App\Http\Controllers\Finance\RentReturnReportController;
use App\Http\Controllers\Finance\RentReturnController;
use App\Http\Controllers\Property\PropertyController;
use App\Http\Controllers\Property\PropertyManagerAssignmentController;
use App\Http\Controllers\Property\PropertyOwnerController;
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
    Route::get('/finance', RentDashboardController::class)->name('finance.index');
    Route::get('/finance/reports/rent-returns', [RentReturnReportController::class, 'index'])->name('finance.reports.rent-returns.index');
    Route::get('/finance/reports/rent-returns.csv', [RentReturnReportController::class, 'csv'])->name('finance.reports.rent-returns.csv');
    Route::get('/finance/reports/rent-returns.pdf', [RentReturnReportController::class, 'pdf'])->name('finance.reports.rent-returns.pdf');
    Route::get('/settings/security', [SecuritySettingsController::class, 'show'])->name('settings.security');
    Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('/properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
    Route::put('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
    Route::delete('/properties/{property}', [PropertyController::class, 'archive'])->name('properties.archive');
    Route::post('/properties/{property}/owners', [PropertyOwnerController::class, 'sync'])->name('properties.owners.sync');
    Route::get('/properties/{property}/finance/ledger', [PropertyLedgerController::class, 'index'])->name('properties.finance.ledger.index');
    Route::get('/properties/{property}/finance/purchase', [PropertyPurchaseController::class, 'show'])->name('properties.finance.purchase.show');
    Route::get('/properties/{property}/finance/sale', [PropertySaleController::class, 'show'])->name('properties.finance.sale.show');
    Route::get('/properties/{property}/finance/reports', [PropertyReportController::class, 'show'])->name('properties.finance.reports.show');
    Route::get('/properties/{property}/finance/reports/owner-statement.csv', [PropertyReportController::class, 'ownerStatementCsv'])->name('properties.finance.reports.owner-statement.csv');
    Route::get('/properties/{property}/finance/reports/owner-statement.pdf', [PropertyReportController::class, 'ownerStatementPdf'])->name('properties.finance.reports.owner-statement.pdf');
    Route::get('/properties/{property}/finance/reports/pnl-matrix.csv', [PropertyReportController::class, 'pnlMatrixCsv'])->name('properties.finance.reports.pnl-matrix.csv');
    Route::get('/properties/{property}/finance/reports/pnl-matrix.pdf', [PropertyReportController::class, 'pnlMatrixPdf'])->name('properties.finance.reports.pnl-matrix.pdf');
    Route::post('/properties/{property}/finance/purchase', [PropertyPurchaseController::class, 'storePurchase'])->name('properties.finance.purchase.store');
    Route::post('/properties/{property}/finance/loan', [PropertyPurchaseController::class, 'storeLoan'])->name('properties.finance.loan.store');
    Route::post('/properties/{property}/finance/loan/{loan}/emis', [PropertyPurchaseController::class, 'storeEmi'])->name('properties.finance.loan.emis.store');
    Route::post('/properties/{property}/finance/sale', [PropertySaleController::class, 'storeListing'])->name('properties.finance.sale.store');
    Route::post('/properties/{property}/finance/sale/{sale}/leads', [PropertySaleController::class, 'storeLead'])->name('properties.finance.sale.leads.store');
    Route::post('/properties/{property}/finance/sale/{sale}/close', [PropertySaleController::class, 'closeSale'])->name('properties.finance.sale.close');
    Route::post('/properties/{property}/finance/ledger/income', [PropertyLedgerController::class, 'storeIncome'])->name('properties.finance.ledger.income.store');
    Route::post('/properties/{property}/finance/ledger/expense', [PropertyLedgerController::class, 'storeExpense'])->name('properties.finance.ledger.expense.store');
    Route::patch('/properties/{property}/finance/ledger/{entry}/review', [PropertyLedgerController::class, 'reviewExpense'])->name('properties.finance.ledger.expense.review');
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
    Route::get('/leases/{lease}/agreement', [LeaseAgreementController::class, 'show'])->name('leases.agreement.show');
    Route::post('/leases/{lease}/agreement', [LeaseAgreementController::class, 'store'])->name('leases.agreement.store');
    Route::post('/leases/{lease}/agreement/{agreement}/verify-integrity', [LeaseAgreementController::class, 'verifyIntegrity'])->name('leases.agreement.verify-integrity');
    Route::get('/leases/{lease}/agreement/{agreement}/signed-pdf', [LeaseAgreementController::class, 'downloadSignedPdf'])->name('leases.agreement.download-signed-pdf');
    Route::get('/leases/{lease}/rent-return/create', [RentReturnController::class, 'create'])->name('leases.rent-return.create');
    Route::post('/leases/{lease}/rent-return', [RentReturnController::class, 'store'])->name('leases.rent-return.store');
    Route::get('/leases/{lease}/rent-return/{rentReturn}', [RentReturnController::class, 'show'])->name('leases.rent-return.show');
    Route::patch('/leases/{lease}/rent-return/{rentReturn}', [RentReturnController::class, 'update'])->name('leases.rent-return.update');
    Route::get('/leases/{lease}/rent-return/{rentReturn}/summary', [RentReturnController::class, 'downloadSummary'])->name('leases.rent-return.summary.download');
    Route::get('/leases/{lease}/payments', [LeasePaymentHistoryController::class, 'show'])->name('leases.payments.show');
    Route::post('/leases/{lease}/payments/{ledger}/instalments', [LeasePaymentHistoryController::class, 'storeInstalment'])->name('leases.payments.instalments.store');
    Route::get('/leases/{lease}/payments/{ledger}/instalments/{instalment}/receipt', [LeasePaymentHistoryController::class, 'downloadReceipt'])->name('leases.payments.receipt.download');
    Route::patch('/leases/{lease}/payments/{ledger}/instalments/{instalment}', [LeasePaymentHistoryController::class, 'correctInstalment'])->name('leases.payments.instalments.correct');
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

    Route::get('/agreements/templates', [AgreementTemplateController::class, 'index'])->name('agreements.templates.index');
    Route::post('/agreements/templates', [AgreementTemplateController::class, 'store'])->name('agreements.templates.store');
    Route::put('/agreements/templates/{template}', [AgreementTemplateController::class, 'update'])->name('agreements.templates.update');
    Route::delete('/agreements/templates/{template}', [AgreementTemplateController::class, 'destroy'])->name('agreements.templates.destroy');

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
        Route::delete('/leases/{lease}/payments/{ledger}/instalments/{instalment}', [LeasePaymentHistoryController::class, 'voidInstalment'])
            ->name('leases.payments.instalments.void');
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
Route::get('/agreement/sign/{token}', [PublicAgreementSigningController::class, 'show'])->name('agreements.public.show');
Route::post('/agreement/sign/{token}', [PublicAgreementSigningController::class, 'sign'])->name('agreements.public.sign');
