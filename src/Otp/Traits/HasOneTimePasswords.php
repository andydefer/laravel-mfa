<?php

declare(strict_types=1);

namespace Kani\Mfa\Otp\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Kani\Mfa\Otp\Data\OtpResponseData;
use Kani\Mfa\Otp\Models\OneTimePassword;
use Kani\Mfa\Otp\Services\OtpService;

/**
 * Trait for Eloquent models that need One-Time Password (OTP) capabilities.
 *
 * Provides a complete OTP management interface including sending, resending,
 * verifying, and canceling OTP codes. Models using this trait gain the
 * ability to generate and verify OTPs with automatic rate limiting and
 * security features.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasOneTimePasswords
{
    /**
     * Define the polymorphic relationship with OTP records.
     *
     * @return MorphMany<OneTimePassword, static>
     */
    public function oneTimePasswords(): MorphMany
    {
        return $this->morphMany(OneTimePassword::class, 'otpable');
    }

    /**
     * Get the OTP service instance from the container.
     */
    protected function getOtpService(): OtpService
    {
        return app(OtpService::class);
    }

    /**
     * Send a new OTP code to the specified destination.
     *
     * @param string      $type              OTP type identifier (e.g., 'login', 'email_verification')
     * @param string      $destination       Delivery destination (email address, phone number)
     * @param array|null  $channels          Preferred delivery channels (e.g., ['email', 'sms'])
     * @param array|null  $meta              Additional metadata to store with the OTP
     * @param int|null    $expiresInMinutes  Custom expiry time in minutes (uses default if null)
     * @param int|null    $maxAttempts       Custom maximum verification attempts (uses default if null)
     */
    public function sendOtp(
        string $type,
        string $destination,
        ?array $channels = null,
        ?array $meta = null,
        ?int $expiresInMinutes = null,
        ?int $maxAttempts = null
    ): OtpResponseData {
        return $this->getOtpService()->send(
            otpable: $this,
            type: $type,
            destination: $destination,
            channels: $channels,
            metadata: $meta,
            expiresInMinutes: $expiresInMinutes,
            maxAttempts: $maxAttempts
        );
    }

    /**
     * Resend an OTP code, cancelling any previous pending OTP.
     *
     * @param string      $type              OTP type identifier
     * @param string      $destination       Delivery destination
     * @param array|null  $channels          Preferred delivery channels (uses previous if null)
     * @param array|null  $meta              Additional metadata (uses previous if null)
     * @param int|null    $expiresInMinutes  Custom expiry time (uses default if null)
     * @param int|null    $maxAttempts       Custom max attempts (uses previous if null)
     */
    public function resendOtp(
        string $type,
        string $destination,
        ?array $channels = null,
        ?array $meta = null,
        ?int $expiresInMinutes = null,
        ?int $maxAttempts = null
    ): OtpResponseData {
        return $this->getOtpService()->resend(
            otpable: $this,
            type: $type,
            destination: $destination,
            channels: $channels,
            metadata: $meta,
            expiresInMinutes: $expiresInMinutes,
            maxAttempts: $maxAttempts
        );
    }

    /**
     * Verify an OTP code.
     *
     * @param string $code        The plain OTP code to verify
     * @param string $type        OTP type identifier
     * @param string $destination Delivery destination
     * @param bool   $consume     Whether to mark the OTP as used after verification
     */
    public function verifyOtp(
        string $code,
        string $type,
        string $destination,
        bool $consume = true
    ): OtpResponseData {
        return $this->getOtpService()->verify(
            otpable: $this,
            code: $code,
            type: $type,
            destination: $destination,
            consume: $consume
        );
    }

    /**
     * Cancel all pending OTPs for a given type and destination.
     *
     * @param string $type        OTP type identifier
     * @param string $destination Delivery destination
     *
     * @return int Number of cancelled OTPs
     */
    public function cancelOtps(string $type, string $destination): int
    {
        return $this->oneTimePasswords()
            ->where('type', $type)
            ->where('destination', $destination)
            ->whereNull('verified_at')
            ->whereNull('used_at')
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => now()]);
    }

    /**
     * Get a pending (valid) OTP for a given type and destination.
     *
     * @param string $type        OTP type identifier
     * @param string $destination Delivery destination
     */
    public function getPendingOtp(string $type, string $destination): ?OneTimePassword
    {
        return $this->oneTimePasswords()
            ->where('type', $type)
            ->where('destination', $destination)
            ->whereNull('verified_at')
            ->whereNull('used_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Check if a valid (non-expired, non-used) OTP exists for the given parameters.
     *
     * @param string $type        OTP type identifier
     * @param string $destination Delivery destination
     */
    public function hasValidOtp(string $type, string $destination): bool
    {
        return $this->getPendingOtp($type, $destination) !== null;
    }

    /**
     * Delete all expired OTPs for this model.
     *
     * @return int Number of deleted OTPs
     */
    public function cleanupExpiredOtps(): int
    {
        return $this->oneTimePasswords()
            ->where('expires_at', '<', now())
            ->whereNull('verified_at')
            ->whereNull('used_at')
            ->delete();
    }
}
