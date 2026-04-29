<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseDepositEntry extends Model
{
    use HasFactory;

    public const ENTRY_TYPES = [
        'collection',
        'top_up',
        'deduction',
        'refund',
        'forfeiture',
    ];

    public const BALANCE_REDUCING_TYPES = [
        'deduction',
        'refund',
        'forfeiture',
    ];

    protected $fillable = [
        'lease_deposit_id',
        'entry_type',
        'amount',
        'notes',
        'created_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function leaseDeposit(): BelongsTo
    {
        return $this->belongsTo(LeaseDeposit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}