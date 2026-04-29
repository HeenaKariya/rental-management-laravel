<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentInstalment extends Model
{
    use HasFactory;

    public const PAYMENT_MODES = [
        'cash',
        'bank_transfer',
        'cheque',
        'upi',
        'other',
    ];

    protected $fillable = [
        'rent_ledger_id',
        'instalment_number',
        'amount_paid',
        'late_fee_charged',
        'payment_date',
        'payment_mode',
        'reference_number',
        'late_fee_waiver_reason',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'late_fee_charged' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(RentLedger::class, 'rent_ledger_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}