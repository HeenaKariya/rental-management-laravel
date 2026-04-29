<?php

return [
    'ttl_minutes' => 10,
    'resend' => [
        'decay_seconds' => 900,
        'max_attempts' => 3,
    ],
    'whatsapp' => [
        'log_channel' => env('AUTH_OTP_WHATSAPP_LOG_CHANNEL', 'stack'),
    ],
];