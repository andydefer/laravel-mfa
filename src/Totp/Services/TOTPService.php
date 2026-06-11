<?php

// src/Totp/Services/TOTPService.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Totp\Services;

use OTPHP\TOTP as OTPHPTOTP;
use ParagonIE\ConstantTime\Base32;

/**
 * Service for TOTP (Time-based One-Time Password) operations.
 *
 * Handles secret generation, code verification, and provisioning URI creation
 * for two-factor authentication using Google Authenticator or compatible apps.
 */
class TOTPService
{
    /**
     * Create a new TOTPService instance.
     *
     * @param  int  $period  Time period in seconds for each code (default: 30)
     * @param  int  $digits  Number of digits in the code (default: 6)
     * @param  string  $algorithm  Hashing algorithm (sha1, sha256, sha512)
     * @param  int  $window  Number of time periods to check (default: 1)
     */
    public function __construct(
        private readonly int $period = 30,
        private readonly int $digits = 6,
        private readonly string $algorithm = 'sha1',
        private readonly int $window = 1
    ) {}

    /**
     * Generate a new TOTP secret in Base32 format.
     *
     * The secret is 20 bytes (160 bits) encoded as Base32, which is the
     * standard length used by Google Authenticator and compatible apps.
     *
     * @return string Base32 encoded secret (uppercase, no padding)
     */
    public function generateSecret(): string
    {
        return Base32::encodeUpper(random_bytes(20));
    }

    /**
     * Create an OTPHP TOTP instance.
     *
     * @param  string  $secret  The shared secret
     */
    private function createTOTP(string $secret): OTPHPTOTP
    {
        return OTPHPTOTP::create(
            secret: $secret,
            period: $this->period,
            digest: $this->algorithm,
            digits: $this->digits
        );
    }

    /**
     * Verify a TOTP code against the shared secret.
     *
     * @param  string  $secret  The shared secret stored for the user
     * @param  string  $code  The 6-digit code from the authenticator app
     * @param  int|null  $window  Number of time periods to check (uses default if null)
     * @param  int|null  $timestamp  Optional timestamp to verify against (useful for testing)
     * @return bool True if the code is valid
     */
    public function verify(string $secret, string $code, ?int $window = null, ?int $timestamp = null): bool
    {
        $totp = $this->createTOTP($secret);

        // Calculate the effective window (use provided window, or service default, or 1 as fallback)
        $effectiveWindow = $window ?? $this->window;

        // Use provided timestamp or current time
        $verificationTime = $timestamp ?? time();

        // For OTPHP library, we need to calculate the time window manually
        // because the library's verify method with timestamp and window parameters
        // might not work as expected

        $currentPeriod = floor($verificationTime / $this->period);

        // Check current period and +/- window periods
        for ($offset = -$effectiveWindow; $offset <= $effectiveWindow; $offset++) {
            $period = $currentPeriod + $offset;
            $timestampForPeriod = (int) $period * $this->period;

            // Generate the expected code for this period
            $expectedCode = $totp->at($timestampForPeriod);

            // Compare with provided code (use timing-safe comparison)
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current TOTP code for testing purposes.
     *
     * @param  string  $secret  The shared secret
     * @param  int|null  $timestamp  Optional timestamp to generate code for (useful for testing)
     * @return string The current 6-digit code
     */
    public function now(string $secret, ?int $timestamp = null): string
    {
        $totp = $this->createTOTP($secret);

        if ($timestamp !== null) {
            return $totp->at($timestamp);
        }

        return $totp->now();
    }
}
