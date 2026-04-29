<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Contracts\WhatsappOtpGateway;
use App\Domain\Auth\Notifications\TwoFactorOtpNotification;
use App\Models\AuthAuditLog;
use App\Models\TwoFactorOtpToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class TwoFactorOtpBroker
{
    public const PURPOSE_LOGIN_CHALLENGE = 'login_challenge';

    public const PURPOSE_SETUP_CONFIRMATION = 'setup_confirmation';

    public function __construct(protected readonly WhatsappOtpGateway $whatsappGateway) {}

    public function ensureActiveToken(User $user, string $purpose): array
    {
        $token = $this->activeToken($user, $purpose);

        if (! $token) {
            return $this->dispatch($user, $purpose);
        }

        return $this->statusPayload($user, $purpose, $token->channel, false, null, $token->expires_at);
    }

    public function dispatch(User $user, string $purpose, bool $isResend = false, ?string $requestedChannel = null): array
    {
        if ($isResend) {
            $this->guardResendLimit($user, $purpose);
        }

        $channels = $this->orderedChannels($user, $requestedChannel);

        if ($channels === []) {
            throw ValidationException::withMessages([
                'code' => ['No OTP delivery channel is available for this account.'],
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $fallbackFrom = null;
        $selectedChannel = null;

        foreach ($channels as $index => $channel) {
            try {
                $this->sendCode($user, $channel, $code, $purpose);
                $selectedChannel = $channel;
                $fallbackFrom = $index > 0 ? $channels[0] : null;
                break;
            } catch (Throwable) {
                continue;
            }
        }

        if (! $selectedChannel) {
            throw ValidationException::withMessages([
                'code' => ['OTP delivery failed on all configured channels.'],
            ]);
        }

        TwoFactorOtpToken::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->delete();

        $expiresAt = now()->addMinutes((int) config('auth-otp.ttl_minutes', 10));

        TwoFactorOtpToken::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $selectedChannel,
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
        ]);

        AuthAuditLog::record($user, $isResend ? 'two_factor.otp_resent' : 'two_factor.otp_sent', [
            'channel' => $selectedChannel,
            'purpose' => $purpose,
        ]);

        if ($fallbackFrom && $fallbackFrom !== $selectedChannel) {
            AuthAuditLog::record($user, 'two_factor.otp_fallback', [
                'from' => $fallbackFrom,
                'purpose' => $purpose,
                'to' => $selectedChannel,
            ]);
        }

        return $this->statusPayload($user, $purpose, $selectedChannel, $isResend, $fallbackFrom, $expiresAt);
    }

    public function verify(User $user, string $code, string $purpose): bool
    {
        $token = $this->activeToken($user, $purpose);

        if (! $token || ! Hash::check($code, $token->code_hash)) {
            return false;
        }

        $token->forceFill([
            'consumed_at' => now(),
        ])->save();

        RateLimiter::clear($this->resendKey($user, $purpose));

        return true;
    }

    protected function activeToken(User $user, string $purpose): ?TwoFactorOtpToken
    {
        return $user->twoFactorOtpTokens()
            ->where('purpose', $purpose)
            ->active()
            ->latest('id')
            ->first();
    }

    protected function orderedChannels(User $user, ?string $requestedChannel = null): array
    {
        $channels = $user->preferredOtpChannels();

        if (! $requestedChannel || ! in_array($requestedChannel, $channels, true)) {
            return $channels;
        }

        return array_values(array_unique([$requestedChannel, ...$channels]));
    }

    protected function sendCode(User $user, string $channel, string $code, string $purpose): void
    {
        $ttlMinutes = (int) config('auth-otp.ttl_minutes', 10);

        if ($channel === 'email') {
            Notification::send($user, new TwoFactorOtpNotification($code, $purpose, $ttlMinutes));

            return;
        }

        if ($channel === 'whatsapp' && $user->phone) {
            $this->whatsappGateway->send(
                $user->phone,
                'Your PropMgr verification code is '.$code.'. It expires in '.$ttlMinutes.' minutes.'
            );

            return;
        }

        throw ValidationException::withMessages([
            'code' => ['The requested OTP delivery channel is unavailable.'],
        ]);
    }

    protected function guardResendLimit(User $user, string $purpose): void
    {
        $key = $this->resendKey($user, $purpose);
        $maxAttempts = (int) config('auth-otp.resend.max_attempts', 3);
        $decaySeconds = (int) config('auth-otp.resend.decay_seconds', 900);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'code' => ['OTP resend is temporarily limited. Try again in '.$retryAfter.' seconds.'],
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    protected function resendKey(User $user, string $purpose): string
    {
        return 'two-factor-otp-resend:'.$user->id.':'.$purpose;
    }

    protected function statusPayload(User $user, string $purpose, string $channel, bool $isResend, ?string $fallbackFrom, $expiresAt): array
    {
        return [
            'availableChannels' => $user->preferredOtpChannels(),
            'channel' => $channel,
            'channelLabel' => $channel === 'whatsapp' ? 'WhatsApp OTP' : 'Email OTP',
            'expiresAt' => $expiresAt,
            'fallbackFrom' => $fallbackFrom,
            'isResend' => $isResend,
            'purpose' => $purpose,
        ];
    }
}
