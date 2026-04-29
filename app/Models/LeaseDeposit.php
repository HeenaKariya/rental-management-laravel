<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeaseDeposit extends Model
{
    use HasFactory;

    public const STATUSES = [
        'open',
        'settled',
        'forfeited',
    ];

    protected $fillable = [
        'lease_id',
        'expected_amount',
        'current_balance',
        'collected_total',
        'top_up_total',
        'deducted_total',
        'refunded_total',
        'forfeited_total',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'collected_total' => 'decimal:2',
            'top_up_total' => 'decimal:2',
            'deducted_total' => 'decimal:2',
            'refunded_total' => 'decimal:2',
            'forfeited_total' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LeaseDepositEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('lease', fn (Builder $leaseQuery) => $leaseQuery->visibleTo($user));
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->lease?->isVisibleTo($user) === true;
    }

    public function postEntry(string $entryType, float $amount, ?User $actor = null, ?string $notes = null): LeaseDepositEntry
    {
        if (! in_array($entryType, LeaseDepositEntry::ENTRY_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported deposit entry type.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit entry amount must be greater than zero.');
        }

        if (in_array($entryType, LeaseDepositEntry::BALANCE_REDUCING_TYPES, true) && (float) $this->current_balance < $amount) {
            throw new InvalidArgumentException('Deposit entry exceeds the current available balance.');
        }

        return DB::transaction(function () use ($entryType, $amount, $actor, $notes): LeaseDepositEntry {
            $entry = $this->entries()->create([
                'entry_type' => $entryType,
                'amount' => $amount,
                'notes' => $notes,
                'created_by' => $actor?->id,
                'occurred_at' => now(),
            ]);

            $this->refreshLedgerTotals($actor);

            return $entry;
        });
    }

    public function refreshLedgerTotals(?User $actor = null): void
    {
        $totals = $this->entries()
            ->selectRaw('entry_type, SUM(amount) as total')
            ->groupBy('entry_type')
            ->pluck('total', 'entry_type');

        $collectedTotal = (float) ($totals['collection'] ?? 0);
        $topUpTotal = (float) ($totals['top_up'] ?? 0);
        $deductedTotal = (float) ($totals['deduction'] ?? 0);
        $refundedTotal = (float) ($totals['refund'] ?? 0);
        $forfeitedTotal = (float) ($totals['forfeiture'] ?? 0);
        $currentBalance = $collectedTotal + $topUpTotal - $deductedTotal - $refundedTotal - $forfeitedTotal;

        $this->forceFill([
            'current_balance' => $currentBalance,
            'collected_total' => $collectedTotal,
            'top_up_total' => $topUpTotal,
            'deducted_total' => $deductedTotal,
            'refunded_total' => $refundedTotal,
            'forfeited_total' => $forfeitedTotal,
            'status' => $forfeitedTotal > 0 && $currentBalance <= 0 ? 'forfeited' : ($currentBalance <= 0 ? 'settled' : 'open'),
            'updated_by' => $actor?->id ?? $this->updated_by,
        ])->save();
    }

    public function reconciles(): bool
    {
        $expectedBalance = (float) $this->collected_total
            + (float) $this->top_up_total
            - (float) $this->deducted_total
            - (float) $this->refunded_total
            - (float) $this->forfeited_total;

        return round($expectedBalance, 2) === round((float) $this->current_balance, 2);
    }
}