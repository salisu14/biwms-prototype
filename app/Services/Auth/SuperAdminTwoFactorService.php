<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditTrailService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SuperAdminTwoFactorService
{
    private const CODE_LENGTH = 6;

    private const PERIOD_SECONDS = 30;

    private const SECRET_LENGTH = 20;

    public function __construct(
        private readonly AuditTrailService $auditTrailService
    ) {}

    public function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($index = 0; $index < self::SECRET_LENGTH; $index++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $secret;
    }

    /**
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn (): string => Str::upper(Str::random(5).'-'.Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return $this->codeForCounter($secret, intdiv($timestamp ?? time(), self::PERIOD_SECONDS));
    }

    public function otpauthUri(User $user, string $secret): string
    {
        $issuer = (string) config('app.name', 'BIWMS');
        $accountName = $user->email ?: $user->name;
        $label = rawurlencode($issuer).':'.rawurlencode($accountName);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::CODE_LENGTH,
            'period' => self::PERIOD_SECONDS,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function qrCodeSvg(string $otpauthUri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($otpauthUri);
    }

    public function verifyCode(string $secret, string $code, ?int $timestamp = null): bool
    {
        $normalizedCode = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($normalizedCode) !== self::CODE_LENGTH) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), self::PERIOD_SECONDS);

        foreach ([-1, 0, 1] as $window) {
            if (hash_equals($this->codeForCounter($secret, $counter + $window), $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalizedCode = Str::upper(trim($code));
        $remainingCodes = [];
        $matched = false;

        foreach ($user->two_factor_recovery_codes ?? [] as $hashedCode) {
            if (! $matched && Hash::check($normalizedCode, $hashedCode)) {
                $matched = true;

                continue;
            }

            $remainingCodes[] = $hashedCode;
        }

        if (! $matched) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $remainingCodes,
            'two_factor_last_challenged_at' => now(),
        ])->save();

        $this->auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_recovery_code_used',
            auditable: $user,
            userId: $user->id,
            description: '2FA recovery code used',
            metadata: ['recovery_codes_remaining' => count($remainingCodes)],
        );

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function enable(User $user, string $secret, ?int $actorId = null): array
    {
        $plainRecoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($plainRecoveryCodes),
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled_by' => $actorId ?? $user->id,
            'two_factor_disabled_at' => null,
            'two_factor_disabled_by' => null,
            'two_factor_reset_at' => null,
            'two_factor_reset_by' => null,
        ])->save();

        return $plainRecoveryCodes;
    }

    public function disable(User $user, ?int $actorId = null): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_disabled_at' => now(),
            'two_factor_disabled_by' => $actorId,
        ])->save();
    }

    public function forceReset(User $user, ?int $actorId = null): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_reset_at' => now(),
            'two_factor_reset_by' => $actorId,
        ])->save();
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $plainRecoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($plainRecoveryCodes),
        ])->save();

        return $plainRecoveryCodes;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return collect($codes)
            ->map(fn (string $code): string => Hash::make(Str::upper(trim($code))))
            ->all();
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = unpack('N', substr($hash, $offset, 4));

        if ($truncatedHash === false) {
            throw new RuntimeException('Unable to generate a two-factor authentication code.');
        }

        $value = ($truncatedHash[1] & 0x7FFFFFFF) % (10 ** self::CODE_LENGTH);

        return str_pad((string) $value, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = Str::upper(str_replace('=', '', $secret));
        $bits = '';

        foreach (str_split($secret) as $character) {
            $position = strpos($alphabet, $character);

            if ($position === false) {
                throw new RuntimeException('Invalid two-factor authentication secret.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}
