<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'purchase_price',
        'purchase_date',
        'stamp_duty',
        'registration_charges',
        'other_acquisition_costs',
        'total_acquisition_cost',
        'seller_name',
        'seller_contact',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'other_acquisition_costs' => 'decimal:2',
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'registration_charges' => 'decimal:2',
            'stamp_duty' => 'decimal:2',
            'total_acquisition_cost' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
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