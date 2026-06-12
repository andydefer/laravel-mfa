<?php

// src/Configs/MfaConfig.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Configs;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Mfa\Collections\SupportedLocaleCollection;
use AndyDefer\Mfa\Contracts\Configs\MfaConfigInterface;
use AndyDefer\Mfa\Enums\SupportedLocale;
use AndyDefer\Mfa\Records\CleanupConfigRecord;
use AndyDefer\Mfa\Records\OtpConfigRecord;
use AndyDefer\Mfa\Records\OtpLocalizationConfigRecord;
use AndyDefer\Mfa\Records\OtpSecurityConfigRecord;
use AndyDefer\Mfa\Records\RecoveryCodesConfigRecord;
use AndyDefer\Mfa\Records\TotpConfigRecord;

class MfaConfig implements MfaConfigInterface
{
    private HydrationService $hydration;

    public function __construct()
    {
        $this->hydration = new HydrationService;
    }

    public function otpConfig(): OtpConfigRecord
    {
        return $this->hydration->hydrate(OtpConfigRecord::class, [
            'default_expiry_minutes' => (int) config('mfa.otp.default_expiry_minutes', 10),
            'default_max_attempts' => (int) config('mfa.otp.default_max_attempts', 3),
        ]);
    }

    public function otpLocalizationConfig(): OtpLocalizationConfigRecord
    {
        $supportedLocales = SupportedLocaleCollection::fromStrings(
            config('mfa.otp.localization.supported_locales', ['fr', 'en'])
        );

        $fallbackLocale = SupportedLocale::fromString(
            config('mfa.otp.localization.fallback_locale', 'en')
        ) ?? SupportedLocale::ENGLISH;

        return $this->hydration->hydrate(OtpLocalizationConfigRecord::class, [
            'locale' => config('mfa.otp.localization.locale', 'en'),
            'supported_locales' => $supportedLocales,
            'fallback_locale' => $fallbackLocale,
        ]);
    }

    public function otpSecurityConfig(): OtpSecurityConfigRecord
    {
        return $this->hydration->hydrate(OtpSecurityConfigRecord::class, [
            'rate_limit_requests' => (int) config('mfa.otp.security.rate_limit_requests', 3),
            'rate_limit_verifications' => (int) config('mfa.otp.security.rate_limit_verifications', 5),
            'rate_limit_decay_minutes' => (int) config('mfa.otp.security.rate_limit_decay_minutes', 60),
            'failed_verification_decay_seconds' => (int) config('mfa.otp.security.failed_verification_decay_seconds', 300),
            'rate_limit_hit_decay_seconds' => (int) config('mfa.otp.security.rate_limit_hit_decay_seconds', 60),
        ]);
    }

    public function totpConfig(): TotpConfigRecord
    {
        return $this->hydration->hydrate(TotpConfigRecord::class, [
            'period' => (int) config('mfa.totp.period', 30),
            'digits' => (int) config('mfa.totp.digits', 6),
            'algorithm' => config('mfa.totp.algorithm', 'sha1'),
            'issuer' => config('mfa.totp.issuer', config('app.name')),
            'window' => (int) config('mfa.totp.window', 1),
        ]);
    }

    public function recoveryCodesConfig(): RecoveryCodesConfigRecord
    {
        return $this->hydration->hydrate(RecoveryCodesConfigRecord::class, [
            'characters' => config('mfa.recovery_codes.characters', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'),
            'default_count' => (int) config('mfa.recovery_codes.default_count', 8),
            'default_length' => (int) config('mfa.recovery_codes.default_length', 10),
            'hash_algorithm' => config('mfa.recovery_codes.hash_algorithm', 'sha256'),
        ]);
    }

    public function cleanupConfig(): CleanupConfigRecord
    {
        return $this->hydration->hydrate(CleanupConfigRecord::class, [
            'auto_cleanup' => (bool) config('mfa.cleanup.auto_cleanup', true),
            'frequency' => (int) config('mfa.cleanup.frequency', 60),
            'retention_days' => (int) config('mfa.cleanup.retention_days', 30),
        ]);
    }

    public function shouldCleanup(): bool
    {
        $config = $this->cleanupConfig();

        return $config->auto_cleanup && $config->frequency > 0;
    }
}
