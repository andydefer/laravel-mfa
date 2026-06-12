<?php

// src/Totp/Traits/HasTwoFactorAuthentication.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Totp\Traits;

use AndyDefer\Mfa\Totp\Models\TwoFactorSecret;
use AndyDefer\Mfa\Totp\Services\TOTPService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Trait for Eloquent models that require Two-Factor Authentication (2FA).
 *
 * Provides a complete 2FA management interface including:
 * - Secret generation and QR code provisioning
 * - Enabling/disabling 2FA with code verification
 * - Recovery code generation and verification
 * - Automatic handling of the polymorphic relationship
 *
 * @mixin Model
 */
trait HasTwoFactorAuthentication
{
    /**
     * Define the polymorphic relationship with the two-factor secret.
     *
     * @return MorphOne
     */
    public function twoFactorSecret()
    {
        return $this->morphOne(TwoFactorSecret::class, 'authenticatable');
    }

    /**
     * Get or create the TOTP secret for this model.
     *
     * If no secret exists, a new one is generated automatically.
     */
    public function getTwoFactorSecret(): TwoFactorSecret
    {
        // Load the relationship if not already loaded
        if (! $this->relationLoaded('twoFactorSecret')) {
            $this->load('twoFactorSecret');
        }

        $secret = $this->twoFactorSecret;

        if (! $secret) {
            $service = app(TOTPService::class);
            $plainSecret = $service->generateSecret();

            $secret = $this->twoFactorSecret()->create([
                'secret' => $plainSecret,
                'label' => $this->email ?? (string) $this->getKey(),
                'issuer' => config('app.name', 'Laravel'),
                'is_enabled' => false,
            ]);

            // Refresh the relationship
            $this->load('twoFactorSecret');
        }

        return $secret;
    }

    /**
     * Check if two-factor authentication is enabled for this model.
     */
    public function isTwoFactorEnabled(): bool
    {
        // Load the relationship if not already loaded
        if (! $this->relationLoaded('twoFactorSecret')) {
            $this->load('twoFactorSecret');
        }

        return $this->twoFactorSecret && $this->twoFactorSecret->is_enabled === true;
    }

    /**
     * Enable two-factor authentication after verifying the TOTP code.
     *
     * @param  string  $code  The 6-digit code from Google Authenticator
     * @return bool True if the code was valid and 2FA was enabled
     */
    public function enableTwoFactor(string $code): bool
    {
        $secret = $this->getTwoFactorSecret();

        // Verify the code using the secret's verifyCode method
        if (! $secret->verifyCode($code)) {
            return false;
        }

        $secret->enable();

        return true;
    }

    /**
     * Disable two-factor authentication for this model.
     *
     * @return bool True if 2FA was disabled, false if it wasn't enabled
     */
    public function disableTwoFactor(): bool
    {
        if (! $this->isTwoFactorEnabled()) {
            return false;
        }

        $this->twoFactorSecret->disable();

        return true;
    }

    /**
     * Verify a TOTP code or recovery code during login.
     *
     * If 2FA is not enabled, this method always returns true.
     * The verification timestamp is updated on successful TOTP verification.
     *
     * @param  string  $code  The 6-digit code or recovery code
     * @return bool True if the code is valid
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (! $this->isTwoFactorEnabled()) {
            return true;
        }

        $secret = $this->twoFactorSecret;

        // Verify TOTP code
        if ($secret->verifyCode($code)) {
            $secret->update(['last_used_at' => now()]);

            return true;
        }

        // Verify recovery code
        if ($secret->verifyRecoveryCode($code)) {
            return true;
        }

        return false;
    }

    /**
     * Get the QR code provisioning URI for the TOTP secret.
     *
     * This URI can be used to generate a QR code that the user scans
     * with Google Authenticator or any compatible app.
     */
    public function getTwoFactorQrCodeUri(): string
    {
        return $this->getTwoFactorSecret()->getProvisioningUri();
    }

    /**
     * Generate new recovery codes for the user.
     *
     * This should be used after enabling 2FA or when the user requests
     * new recovery codes. Returns the plain text codes (show once).
     */
    public function generateRecoveryCodes(): array
    {
        return $this->getTwoFactorSecret()->generateRecoveryCodes();
    }

    /**
     * Get the hashed recovery codes (for debugging only).
     */
    public function getRecoveryCodes(): array
    {
        if (! $this->relationLoaded('twoFactorSecret')) {
            $this->load('twoFactorSecret');
        }

        return $this->twoFactorSecret?->recovery_codes ?? [];
    }
}
