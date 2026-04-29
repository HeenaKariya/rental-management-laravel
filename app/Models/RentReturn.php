<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RentReturn extends Model
{
    use HasFactory;

    public const STATUSES = [
        'initiated',
        'confirmed',
        'settled',
        'waived',
        'pending_settlement',
    ];

    public const SETTLEMENT_METHODS = [
        'cash_refund',
        'adjust_arrears',
        'adjust_deposit',
        'write_off',
        'pending_tbd',
    ];

    protected $fillable = [
        'lease_id',
        'tenant_id',
        'unit_id',
        'property_id',
        'vacation_date',
        'last_paid_through_date',
        'billing_month',
        'daily_rate',
        'unused_days',
        'suggested_amount',
        'confirmed_amount',
        'override_reason',
        'status',
        'settlement_method',
        'settlement_amount',
        'settlement_date',
        'settlement_reference',
        'settlement_details',
        'ledger_posted',
        'notes',
        'initiated_by',
        'initiated_at',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'confirmed_amount' => 'decimal:2',
            'daily_rate' => 'decimal:4',
            'initiated_at' => 'datetime',
            'last_paid_through_date' => 'date',
            'ledger_posted' => 'boolean',
            'processed_at' => 'datetime',
            'settlement_amount' => 'decimal:2',
            'settlement_date' => 'date',
            'suggested_amount' => 'decimal:2',
            'unused_days' => 'integer',
            'vacation_date' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('lease', fn (Builder $leaseQuery) => $leaseQuery->visibleTo($user));
    }

    public function canDownloadSummary(): bool
    {
        return in_array($this->status, ['confirmed', 'settled', 'waived', 'pending_settlement'], true);
    }

    public static function calculateSuggestion(Carbon $vacationDate, ?Carbon $lastPaidThroughDate, float $monthlyRentAmount, int $billingMonthDays): array
    {
        $unusedDays = $lastPaidThroughDate && $lastPaidThroughDate->gt($vacationDate)
            ? $vacationDate->diffInDays($lastPaidThroughDate)
            : 0;
        $dailyRate = $billingMonthDays > 0
            ? round($monthlyRentAmount / $billingMonthDays, 4)
            : 0.0;

        return [
            'daily_rate' => $dailyRate,
            'suggested_amount' => round($dailyRate * $unusedDays, 2),
            'unused_days' => $unusedDays,
        ];
    }
}