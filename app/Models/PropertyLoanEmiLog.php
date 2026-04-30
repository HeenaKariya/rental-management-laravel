<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyLoanEmiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_loan_id',
        'emi_number',
        'amount_paid',
        'date_paid',
        'principal_component',
        'interest_component',
        'outstanding_balance',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'date_paid' => 'date',
            'emi_number' => 'integer',
            'interest_component' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'principal_component' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(PropertyLoan::class, 'property_loan_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}