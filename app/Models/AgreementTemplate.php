<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgreementTemplate extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'name',
        'body_html',
        'status',
        'created_by',
        'updated_by',
    ];

    public function agreements(): HasMany
    {
        return $this->hasMany(RentAgreement::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}