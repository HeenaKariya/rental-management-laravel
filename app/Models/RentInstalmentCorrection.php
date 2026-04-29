<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentInstalmentCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'rent_instalment_id',
        'field_name',
        'old_value',
        'new_value',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function instalment(): BelongsTo
    {
        return $this->belongsTo(RentInstalment::class, 'rent_instalment_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}