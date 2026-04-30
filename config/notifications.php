<?php

return [
    'whatsapp' => [
        'driver' => env('NOTIFICATIONS_WHATSAPP_DRIVER', 'log'),
        'log_channel' => env('NOTIFICATIONS_WHATSAPP_LOG_CHANNEL', 'stack'),
        'timeout_seconds' => (int) env('NOTIFICATIONS_WHATSAPP_TIMEOUT_SECONDS', 10),
        'verify_ssl' => filter_var(env('NOTIFICATIONS_WHATSAPP_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
        'ca_bundle' => env('NOTIFICATIONS_WHATSAPP_CA_BUNDLE'),
        'wpsms' => [
            'endpoint' => env('WPSMS_WHATSAPP_ENDPOINT', 'https://wpsms.configured.cc/api/send/whatsapp'),
            'method' => env('WPSMS_WHATSAPP_METHOD', 'POST'),
            'secret' => env('WPSMS_API_SECRET'),
            'account' => env('WPSMS_WHATSAPP_ACCOUNT'),
            'phone_field' => env('WPSMS_WHATSAPP_PHONE_FIELD', 'recipient'),
            'message_field' => env('WPSMS_WHATSAPP_MESSAGE_FIELD', 'message'),
            'type_field' => env('WPSMS_WHATSAPP_TYPE_FIELD', 'type'),
            'type_value' => env('WPSMS_WHATSAPP_TYPE_VALUE', 'text'),
            'secret_field' => env('WPSMS_WHATSAPP_SECRET_FIELD', 'secret'),
            'account_field' => env('WPSMS_WHATSAPP_ACCOUNT_FIELD', 'account'),
            'priority_field' => env('WPSMS_WHATSAPP_PRIORITY_FIELD', 'priority'),
            'priority_value' => env('WPSMS_WHATSAPP_PRIORITY_VALUE'),
        ],
    ],
];
