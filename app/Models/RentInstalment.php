<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'late_fee_charged' => 'decimal:2',
            'payment_date' => 'date',
            'voided_at' => 'datetime',
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

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(RentInstalmentCorrection::class)->latest('changed_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function correctMetadata(array $changes, User $actor): void
    {
        if ($this->isVoided()) {
            throw new InvalidArgumentException('Voided instalments are terminal and cannot be corrected.');
        }

        $supportedFields = ['payment_mode', 'reference_number'];

        DB::transaction(function () use ($changes, $actor, $supportedFields): void {
            $dirtyPayload = [];

            foreach ($supportedFields as $fieldName) {
                if (! array_key_exists($fieldName, $changes)) {
                    continue;
                }

                $newValue = $changes[$fieldName];
                $oldValue = $this->{$fieldName};

                if ((string) $oldValue === (string) $newValue) {
                    continue;
                }

                $dirtyPayload[$fieldName] = $newValue;
                $this->corrections()->create([
                    'field_name' => $fieldName,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'changed_by' => $actor->id,
                    'changed_at' => now(),
                ]);
            }

            if ($dirtyPayload !== []) {
                $this->forceFill($dirtyPayload)->save();
            }
        });
    }

    public function void(string $reason, User $actor): void
    {
        if ($this->isVoided()) {
            throw new InvalidArgumentException('This instalment has already been voided.');
        }

        DB::transaction(function () use ($reason, $actor): void {
            $this->forceFill([
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();

            $this->corrections()->create([
                'field_name' => 'voided',
                'old_value' => 'active',
                'new_value' => $reason,
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            $this->ledger->lease->syncRentLedgerTimeline($actor);
        });
    }
}