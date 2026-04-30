<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'property_id',
        'actor_id',
        'subject_user_id',
        'event',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public static function record(Property $property, string $event, ?User $actor = null, array $context = [], ?User $subjectUser = null): self
    {
        return static::query()->create([
            'property_id' => $property->id,
            'actor_id' => $actor?->id,
            'subject_user_id' => $subjectUser?->id,
            'event' => $event,
            'context' => $context === [] ? null : $context,
            'occurred_at' => now(),
        ]);
    }

    public function label(): string
    {
        return match ($this->event) {
            'property.created' => 'Property created',
            'property.updated' => 'Property updated',
            'property.lifecycle_changed' => 'Lifecycle changed',
            'property.archived' => 'Property archived',
            'property.manager_assigned' => 'Manager assigned',
            'property.manager_revoked' => 'Manager revoked',
            'property.ownership_updated' => 'Ownership updated',
            'property.purchase_updated' => 'Purchase details updated',
            'property.loan_updated' => 'Loan details updated',
            'property.loan_emi_recorded' => 'EMI payment recorded',
            'property.sale_listed' => 'Sale listing updated',
            'property.sale_lead_logged' => 'Sale lead logged',
            'property.sale_closed' => 'Sale closed',
            'property.agreement_generated' => 'Agreement generated',
            'property.agreement_signed' => 'Agreement signed',
            'property.agreement_integrity_verified' => 'Agreement integrity verified',
            'property.agreement_integrity_failed' => 'Agreement integrity failed',
            default => str($this->event)->replace('.', ' ')->title()->toString(),
        };
    }

    public function badgeClass(): string
    {
        return match ($this->event) {
            'property.archived' => 'badge-coral',
            'property.lifecycle_changed' => 'badge-violet',
            'property.manager_assigned' => 'badge-green',
            'property.manager_revoked' => 'badge-gold',
            'property.agreement_integrity_failed' => 'badge-coral',
            'property.agreement_integrity_verified' => 'badge-green',
            default => 'badge-outline',
        };
    }
}
