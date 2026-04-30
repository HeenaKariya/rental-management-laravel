<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\Services\WpsmsWhatsappNotificationGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WpsmsWhatsappNotificationGatewayTest extends TestCase
{
    public function test_it_sends_whatsapp_payload_to_configured_wpsms_endpoint(): void
    {
        config()->set('notifications.whatsapp.timeout_seconds', 10);
        config()->set('notifications.whatsapp.wpsms', [
            'endpoint' => 'https://wpsms.example.test/api/send/whatsapp',
            'method' => 'POST',
            'secret' => 'secret-key',
            'account' => 'WA-UNIQUE-123',
            'secret_field' => 'secret',
            'account_field' => 'account',
            'phone_field' => 'recipient',
            'message_field' => 'message',
            'type_field' => 'type',
            'type_value' => 'text',
            'priority_field' => 'priority',
            'priority_value' => '1',
        ]);

        Http::fake([
            'https://wpsms.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $gateway = new WpsmsWhatsappNotificationGateway();
        $gateway->send('+15550001234', 'Lease reminder message');

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://wpsms.example.test/api/send/whatsapp') {
                return false;
            }

            if ($request->method() !== 'POST') {
                return false;
            }

            $multipart = $request->toPsrRequest()->getBody()->getContents();

            return str_contains($multipart, 'name="secret"')
                && str_contains($multipart, 'secret-key')
                && str_contains($multipart, 'name="account"')
                && str_contains($multipart, 'WA-UNIQUE-123')
                && str_contains($multipart, 'name="recipient"')
                && str_contains($multipart, '+15550001234')
                && str_contains($multipart, 'name="message"')
                && str_contains($multipart, 'Lease reminder message')
                && str_contains($multipart, 'name="type"')
                && str_contains($multipart, 'text')
                && str_contains($multipart, 'name="priority"')
                && str_contains($multipart, '1');
        });
    }

    public function test_it_throws_when_wpsms_returns_a_failure_response(): void
    {
        config()->set('notifications.whatsapp.timeout_seconds', 10);
        config()->set('notifications.whatsapp.wpsms', [
            'endpoint' => 'https://wpsms.example.test/api/send/whatsapp',
            'method' => 'POST',
            'secret' => 'secret-key',
            'account' => 'WA-UNIQUE-123',
            'secret_field' => 'secret',
            'account_field' => 'account',
            'phone_field' => 'recipient',
            'message_field' => 'message',
            'type_field' => 'type',
            'type_value' => 'text',
            'priority_field' => 'priority',
            'priority_value' => null,
        ]);

        Http::fake([
            'https://wpsms.example.test/*' => Http::response(['error' => 'invalid'], 422),
        ]);

        $gateway = new WpsmsWhatsappNotificationGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WPSMS WhatsApp API request failed');

        $gateway->send('+15550001234', 'Lease reminder message');
    }
}
