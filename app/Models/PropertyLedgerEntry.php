<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PropertyLedgerEntry extends Model
{
    use HasFactory;

    public const ENTRY_TYPES = ['income', 'expense'];
    public const EXPENSE_CATEGORIES = ['maintenance', 'loan_emi', 'property_tax', 'insurance', 'management_fee', 'utility', 'other'];
    public const STATUSES = ['approved', 'pending_review', 'rejected'];

    protected $fillable = [
        'property_id',
        'entry_type',
        'entry_date',
        'category',
        'amount',
        'vendor_name',
        'reference_number',
        'notes',
        'source_type',
        'source_id',
        'status',
        'flagged_reason',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'entry_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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
        return $query->whereHas('property', fn (Builder $propertyQuery) => $propertyQuery->visibleTo($user));
    }

    public static function shouldFlagExpense(float $amount, string $category): ?string
    {
        if ($amount >= 50000) {
            return 'High-value expense entry requires Super Admin review.';
        }

        if ($category === 'other' && $amount >= 20000) {
            return 'Other-category expense above policy threshold requires review.';
        }

        return null;
    }

    public static function recordRentInstalment(RentInstalment $instalment, User $actor): self
    {
        $instalment->loadMissing('ledger.lease.unit.property');

        return static::query()->firstOrCreate(
            [
                'source_type' => $instalment::class,
                'source_id' => $instalment->id,
                'entry_type' => 'income',
                'category' => 'rent_payment',
            ],
            [
                'property_id' => $instalment->ledger->lease->unit->property_id,
                'entry_date' => $instalment->payment_date->toDateString(),
                'amount' => (float) $instalment->amount_paid,
                'reference_number' => $instalment->reference_number,
                'notes' => 'Auto posted from rent instalment #'.$instalment->instalment_number,
                'status' => 'approved',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
    }

    public static function recordRentReversal(RentInstalment $instalment, User $actor): self
    {
        $instalment->loadMissing('ledger.lease.unit.property');

        return static::query()->firstOrCreate(
            [
                'source_type' => $instalment::class,
                'source_id' => $instalment->id,
                'entry_type' => 'income',
                'category' => 'rent_reversal',
            ],
            [
                'property_id' => $instalment->ledger->lease->unit->property_id,
                'entry_date' => now()->toDateString(),
                'amount' => ((float) $instalment->amount_paid) * -1,
                'reference_number' => $instalment->reference_number,
                'notes' => 'Auto posted from voided rent instalment #'.$instalment->instalment_number,
                'status' => 'approved',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
    }
}