<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\WhatsappNotificationGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WpsmsWhatsappNotificationGateway implements WhatsappNotificationGateway
{
    public function send(string $phone, string $message): void
    {
        $config = config('notifications.whatsapp.wpsms', []);
        $endpoint = (string) ($config['endpoint'] ?? '');

        if ($endpoint === '') {
            throw new RuntimeException('WPSMS WhatsApp endpoint is not configured.');
        }

        $secret = (string) ($config['secret'] ?? '');
        $account = (string) ($config['account'] ?? '');

        if ($secret === '') {
            throw new RuntimeException('WPSMS API secret is not configured.');
        }

        if ($account === '') {
            throw new RuntimeException('WPSMS WhatsApp account unique ID is not configured.');
        }

        $secretField = (string) ($config['secret_field'] ?? 'secret');
        $accountField = (string) ($config['account_field'] ?? 'account');
        $phoneField = (string) ($config['phone_field'] ?? 'recipient');
        $messageField = (string) ($config['message_field'] ?? 'message');
        $typeField = (string) ($config['type_field'] ?? 'type');
        $typeValue = (string) ($config['type_value'] ?? 'text');
        $priorityField = (string) ($config['priority_field'] ?? 'priority');
        $priorityValue = $config['priority_value'] ?? null;

        $payload = [
            $secretField => $secret,
            $accountField => $account,
            $phoneField => $phone,
            $typeField => $typeValue,
            $messageField => $message,
        ];

        if (filled($priorityValue)) {
            $payload[$priorityField] = $priorityValue;
        }

        $timeoutSeconds = (int) config('notifications.whatsapp.timeout_seconds', 10);
        $verifySsl = config('notifications.whatsapp.verify_ssl', true);
        $caBundle = config('notifications.whatsapp.ca_bundle');
        $method = strtoupper((string) ($config['method'] ?? 'POST'));

        $http = Http::timeout(max($timeoutSeconds, 1))
            ->acceptJson()
            ->asMultipart();

        if (is_string($caBundle) && $caBundle !== '') {
            $http = $http->withOptions(['verify' => $caBundle]);
        } elseif ($verifySsl === false) {
            $http = $http->withoutVerifying();
        }

        $response = $http
            ->send($method, $endpoint, [
                'multipart' => collect($payload)
                    ->map(fn ($value, $key) => ['name' => $key, 'contents' => (string) $value])
                    ->values()
                    ->all(),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WPSMS WhatsApp API request failed with status '.$response->status().'.');
        }
    }
}
