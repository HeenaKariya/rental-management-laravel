<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'lender_name',
        'loan_amount',
        'interest_rate',
        'interest_rate_type',
        'loan_start_date',
        'tenure_months',
        'emi_amount',
        'emi_due_day',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'emi_amount' => 'decimal:2',
            'interest_rate' => 'decimal:3',
            'loan_amount' => 'decimal:2',
            'loan_start_date' => 'date',
            'tenure_months' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function emiLogs(): HasMany
    {
        return $this->hasMany(PropertyLoanEmiLog::class)->orderBy('emi_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function summary(): array
    {
        $emiLogs = $this->emiLogs;
        $lastOutstanding = (float) ($emiLogs->last()?->outstanding_balance ?? $this->loan_amount);

        return [
            'remaining_tenure_months' => max($this->tenure_months - $emiLogs->count(), 0),
            'outstanding_principal' => $lastOutstanding,
            'total_emis_paid' => (float) $emiLogs->sum(fn (PropertyLoanEmiLog $emiLog) => (float) $emiLog->amount_paid),
            'total_interest_paid' => (float) $emiLogs->sum(fn (PropertyLoanEmiLog $emiLog) => (float) $emiLog->interest_component),
        ];
    }
}