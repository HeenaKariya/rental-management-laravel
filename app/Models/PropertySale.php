<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertySale extends Model
{
    use HasFactory;

    public const STATUSES = ['for_sale', 'closed'];

    protected $fillable = [
        'property_id',
        'listing_date',
        'asking_price',
        'broker_name',
        'broker_contact',
        'listing_notes',
        'status',
        'final_sale_price',
        'sale_date',
        'buyer_name',
        'buyer_contact',
        'sale_deed_path',
        'broker_commission',
        'closing_costs',
        'sale_notes',
        'total_acquisition_cost_snapshot',
        'net_sale_proceeds',
        'gross_profit_loss',
        'closed_by',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'asking_price' => 'decimal:2',
            'broker_commission' => 'decimal:2',
            'closed_at' => 'datetime',
            'closing_costs' => 'decimal:2',
            'final_sale_price' => 'decimal:2',
            'gross_profit_loss' => 'decimal:2',
            'listing_date' => 'date',
            'net_sale_proceeds' => 'decimal:2',
            'sale_date' => 'date',
            'total_acquisition_cost_snapshot' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(PropertySaleLead::class)->latest('inquiry_date');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function ownerShares(): array
    {
        $owners = $this->property->owners()->where('is_active', true)->with('user')->get();
        $gross = (float) ($this->gross_profit_loss ?? 0);

        return $owners->map(function (PropertyOwner $owner) use ($gross): array {
            return [
                'owner' => $owner,
                'share_amount' => round($gross * ((float) $owner->ownership_pct / 100), 2),
            ];
        })->values()->all();
    }
}