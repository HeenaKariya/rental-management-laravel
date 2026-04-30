<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentAgreement extends Model
{
    use HasFactory;

    public const STATUSES = ['generated', 'viewed', 'signed', 'voided'];
    public const INTEGRITY_STATUSES = ['verified', 'tampered'];

    protected $fillable = [
        'lease_id',
        'tenant_id',
        'template_id',
        'generated_content',
        'token',
        'status',
        'first_viewed_at',
        'signed_at',
        'signing_ip',
        'signing_device',
        'signing_method',
        'signature_label',
        'signed_pdf_path',
        'signed_pdf_hash',
        'signed_content_hash',
        'integrity_last_checked_at',
        'integrity_check_status',
        'integrity_checked_by',
        'integrity_check_notes',
        'voided_at',
        'voided_by',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'first_viewed_at' => 'datetime',
            'integrity_last_checked_at' => 'datetime',
            'signed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AgreementTemplate::class, 'template_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function integrityChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'integrity_checked_by');
    }

    public function isUnsigned(): bool
    {
        return in_array($this->status, ['generated', 'viewed'], true);
    }
}