<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationEventSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'is_enabled',
        'email_enabled',
        'whatsapp_enabled',
        'lead_days',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'lead_days' => 'integer',
        ];
    }

    public static function enabledFor(string $eventKey, int $defaultLeadDays = 0): array
    {
        $setting = static::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'is_enabled' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'lead_days' => max($defaultLeadDays, 0),
            ]
        );

        return [
            'is_enabled' => (bool) $setting->is_enabled,
            'email_enabled' => (bool) $setting->email_enabled,
            'whatsapp_enabled' => (bool) $setting->whatsapp_enabled,
            'lead_days' => (int) $setting->lead_days,
        ];
    }
}
