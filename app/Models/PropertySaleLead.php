<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertySaleLead extends Model
{
    use HasFactory;

    public const STATUSES = ['enquiry', 'offer_made', 'negotiation', 'accepted', 'rejected', 'lapsed'];

    protected $fillable = [
        'property_sale_id',
        'buyer_name',
        'buyer_contact',
        'inquiry_date',
        'offer_amount',
        'offer_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inquiry_date' => 'date',
            'offer_amount' => 'decimal:2',
            'offer_date' => 'date',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PropertySale::class, 'property_sale_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}