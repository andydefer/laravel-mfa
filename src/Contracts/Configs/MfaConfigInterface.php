<?php

// src/Contracts/Configs/MfaConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Contracts\Configs;

use AndyDefer\Mfa\Records\CleanupConfigRecord;
use AndyDefer\Mfa\Records\OtpConfigRecord;
use AndyDefer\Mfa\Records\OtpLocalizationConfigRecord;
use AndyDefer\Mfa\Records\OtpSecurityConfigRecord;
use AndyDefer\Mfa\Records\RecoveryCodesConfigRecord;
use AndyDefer\Mfa\Records\TotpConfigRecord;

interface MfaConfigInterface
{
    /**
     * Get OTP configuration.
     */
    public function otpConfig(): OtpConfigRecord;

    /**
     * Get OTP localization configuration.
     */
    public function otpLocalizationConfig(): OtpLocalizationConfigRecord;

    /**
     * Get OTP security configuration.
     */
    public function otpSecurityConfig(): OtpSecurityConfigRecord;

    /**
     * Get TOTP configuration.
     */
    public function totpConfig(): TotpConfigRecord;

    /**
     * Get recovery codes configuration.
     */
    public function recoveryCodesConfig(): RecoveryCodesConfigRecord;

    /**
     * Get cleanup configuration.
     */
    public function cleanupConfig(): CleanupConfigRecord;

    // ============================================================================
    // Helper Methods
    // ============================================================================

    /**
     * Check if cleanup is enabled and has valid configuration.
     */
    public function shouldCleanup(): bool;
}
