<?php

declare(strict_types=1);

namespace Kani\Otp\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Kani\Otp\Data\OtpResponseData;
use Kani\Otp\Models\OneTimePassword;
use Kani\Otp\Notifications\OtpNotification;

/**
 * Core service for One-Time Password (OTP) operations.
 *
 * Handles the complete OTP lifecycle including:
 * - Sending OTP codes via multiple channels
 * - Resending OTP codes
 * - Verifying OTP codes with attempt tracking
 * - Rate limiting protection for both requests and verifications
 * - Automatic cleanup of pending OTPs when new ones are created
 */
final class OtpService
{
    /**
     * Create a new OTP service instance.
     *
     * @param int $defaultExpiryMinutes Default validity period for OTPs
     * @param int $defaultMaxAttempts Maximum number of verification attempts
     * @param int $rateLimitRequests Maximum OTP requests per time window
     * @param int $rateLimitVerifications Maximum verification attempts per time window
     * @param int $rateLimitDecayMinutes Length of rate limit window in minutes
     */
    public function __construct(
        private readonly int $defaultExpiryMinutes = 10,
        private readonly int $defaultMaxAttempts = 3,
        private readonly int $rateLimitRequests = 3,
        private readonly int $rateLimitVerifications = 5,
        private readonly int $rateLimitDecayMinutes = 60
    ) {}

    /**
     * Generate a random 6-digit OTP code.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Hash a plain code for secure storage.
     */
    private function hashPlainCode(string $plainCode): string
    {
        return Hash::make($plainCode);
    }

    /**
     * Create a new OTP record in the database.
     *
     * @param Model   $otpable         The entity requesting the OTP
     * @param string  $type            OTP type (email_verification, login, 2fa, etc.)
     * @param string  $destination     Delivery destination (email address, phone number)
     * @param array|null $channels     Preferred delivery channels
     * @param array|null $metadata     Additional metadata to store with the OTP
     * @param int     $expiresInMinutes Validity period in minutes
     * @param int     $maxAttempts      Maximum verification attempts allowed
     */
    private function createOtpRecord(
        Model $otpable,
        string $type,
        string $destination,
        ?array $channels,
        ?array $metadata,
        int $expiresInMinutes,
        int $maxAttempts
    ): OneTimePassword {
        $plainCode = $this->generateCode();

        return OneTimePassword::create([
            'otpable_type' => $otpable->getMorphClass(),
            'otpable_id' => $otpable->getKey(),
            'token_hash' => $this->hashPlainCode($plainCode),
            'type' => $type,
            'destination' => $destination,
            'channels' => $channels,
            'meta' => $metadata,
            'max_attempts' => $maxAttempts,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);
    }

    /**
     * Send a new OTP code to the specified destination.
     *
     * @param Model       $otpable           The entity requesting the OTP
     * @param string      $type              OTP type identifier
     * @param string      $destination       Delivery destination
     * @param array|null  $channels          Preferred delivery channels
     * @param array|null  $metadata          Additional metadata
     * @param int|null    $expiresInMinutes  Custom expiry (uses default if null)
     * @param int|null    $maxAttempts       Custom max attempts (uses default if null)
     */
    public function send(
        Model $otpable,
        string $type,
        string $destination,
        ?array $channels = null,
        ?array $metadata = null,
        ?int $expiresInMinutes = null,
        ?int $maxAttempts = null
    ): OtpResponseData {
        $rateLimitKey = $this->buildRequestRateLimitKey($otpable, $type, $destination);

        if ($this->isRateLimitExceeded($rateLimitKey, $this->rateLimitRequests)) {
            $waitSeconds = $this->getAvailableInSeconds($rateLimitKey);

            return OtpResponseData::rateLimited(
                sprintf('Please wait %d seconds before requesting another OTP.', $waitSeconds)
            );
        }

        $this->deleteOldPendingOtps($otpable, $type, $destination);

        $otpRecord = $this->createOtpRecord(
            otpable: $otpable,
            type: $type,
            destination: $destination,
            channels: $channels,
            metadata: $metadata,
            expiresInMinutes: $expiresInMinutes ?? $this->defaultExpiryMinutes,
            maxAttempts: $maxAttempts ?? $this->defaultMaxAttempts
        );

        $notificationSent = $this->sendOtpNotification($otpable, $otpRecord);

        if (!$notificationSent) {
            $otpRecord->delete();

            return OtpResponseData::sendFailed('Unable to send OTP. Please try again.');
        }

        RateLimiter::hit($rateLimitKey, $this->rateLimitDecayMinutes * 60);

        return OtpResponseData::success(
            data: [
                'expires_at' => $otpRecord->expires_at->toIso8601String(),
                'expires_in_minutes' => $expiresInMinutes ?? $this->defaultExpiryMinutes,
            ],
            message: 'Verification code sent successfully.'
        );
    }

    /**
     * Resend an OTP code, cancelling any previous pending OTP.
     *
     * @param Model       $otpable           The entity requesting the OTP
     * @param string      $type              OTP type identifier
     * @param string      $destination       Delivery destination
     * @param array|null  $channels          Preferred delivery channels (uses previous if null)
     * @param array|null  $metadata          Additional metadata (uses previous if null)
     * @param int|null    $expiresInMinutes  Custom expiry (uses default if null)
     * @param int|null    $maxAttempts       Custom max attempts (uses previous if null)
     */
    public function resend(
        Model $otpable,
        string $type,
        string $destination,
        ?array $channels = null,
        ?array $metadata = null,
        ?int $expiresInMinutes = null,
        ?int $maxAttempts = null
    ): OtpResponseData {
        $pendingOtp = $this->findPendingOtp($otpable, $type, $destination);

        if (!$pendingOtp) {
            return $this->send($otpable, $type, $destination, $channels, $metadata, $expiresInMinutes, $maxAttempts);
        }

        $rateLimitKey = $this->buildRequestRateLimitKey($otpable, $type, $destination);

        if ($this->isRateLimitExceeded($rateLimitKey, $this->rateLimitRequests)) {
            $waitSeconds = $this->getAvailableInSeconds($rateLimitKey);

            return OtpResponseData::rateLimited(
                sprintf('Please wait %d seconds before requesting another OTP.', $waitSeconds)
            );
        }

        $newOtpRecord = $this->createOtpRecord(
            otpable: $otpable,
            type: $type,
            destination: $destination,
            channels: $channels ?? $pendingOtp->channels,
            metadata: $metadata ?? $pendingOtp->meta,
            expiresInMinutes: $expiresInMinutes ?? $this->defaultExpiryMinutes,
            maxAttempts: $maxAttempts ?? $pendingOtp->max_attempts
        );

        $pendingOtp->markAsCancelled();

        $notificationSent = $this->sendOtpNotification($otpable, $newOtpRecord);

        if (!$notificationSent) {
            $newOtpRecord->delete();

            return OtpResponseData::resendFailed('Unable to resend OTP. Please try again.');
        }

        RateLimiter::hit($rateLimitKey, $this->rateLimitDecayMinutes * 60);

        return OtpResponseData::success(
            data: [
                'expires_at' => $newOtpRecord->expires_at->toIso8601String(),
                'expires_in_minutes' => $expiresInMinutes ?? $this->defaultExpiryMinutes,
            ],
            message: 'Verification code resent successfully.'
        );
    }

    /**
     * Verify an OTP code.
     *
     * @param Model   $otpable     The entity verifying the OTP
     * @param string  $code        The plain code to verify
     * @param string  $type        OTP type identifier
     * @param string  $destination Delivery destination
     * @param bool    $consume     Whether to mark the OTP as used after verification
     */
    public function verify(
        Model $otpable,
        string $code,
        string $type,
        string $destination,
        bool $consume = true
    ): OtpResponseData {
        $rateLimitKey = $this->buildVerificationRateLimitKey($otpable, $type, $destination);

        if ($this->isRateLimitExceeded($rateLimitKey, $this->rateLimitVerifications)) {
            $waitSeconds = $this->getAvailableInSeconds($rateLimitKey);

            return OtpResponseData::rateLimited(
                sprintf("Too many verification attempts. Please try again in {$waitSeconds} seconds.")
            );
        }

        $otpRecord = $this->findValidOtp($otpable, $type, $destination);

        if (!$otpRecord) {
            RateLimiter::hit($rateLimitKey, 300);

            return OtpResponseData::notFound('Invalid or expired OTP code.');
        }

        if ($otpRecord->isExpired()) {
            $otpRecord->markAsCancelled();
            RateLimiter::hit($rateLimitKey, 300);

            return OtpResponseData::expiredCode('OTP code has expired. Please request a new one.');
        }

        if (!$otpRecord->verifyCode($code)) {
            $otpRecord->incrementAttempts();
            RateLimiter::hit($rateLimitKey, 300);

            $remainingAttempts = $otpRecord->max_attempts - $otpRecord->attempts;

            if ($otpRecord->hasExceededMaxAttempts()) {
                $otpRecord->markAsCancelled();

                return OtpResponseData::maxAttemptsExceeded(
                    'Maximum verification attempts exceeded. Please request a new OTP.'
                );
            }

            $message = $remainingAttempts > 1
                ? "Invalid OTP code. You have {$remainingAttempts} attempts remaining."
                : 'Invalid OTP code. You have 1 attempt remaining.';

            return OtpResponseData::invalidCode($message);
        }

        $otpRecord->markAsVerified();

        if ($consume) {
            $otpRecord->markAsUsed();
        }

        RateLimiter::clear($rateLimitKey);
        RateLimiter::clear($this->buildRequestRateLimitKey($otpable, $type, $destination));

        return OtpResponseData::success(
            data: ['meta' => $otpRecord->meta],
            message: 'OTP verified successfully.'
        );
    }

    /**
     * Cancel all pending OTPs for a given entity, type, and destination.
     *
     * @param Model  $otpable     The entity whose OTPs to cancel
     * @param string $type        OTP type identifier
     * @param string $destination Delivery destination
     */
    public function cancel(Model $otpable, string $type, string $destination): OtpResponseData
    {
        $cancelledCount = $otpable->cancelOtps($type, $destination);

        $message = $cancelledCount > 0
            ? "{$cancelledCount} OTP(s) cancelled successfully."
            : 'No pending OTPs found to cancel.';

        return OtpResponseData::success(
            data: ['cancelled_count' => $cancelledCount],
            message: $message
        );
    }

    /**
     * Send the OTP notification to the entity.
     */
    private function sendOtpNotification(Model $otpable, OneTimePassword $otpRecord): bool
    {
        try {
            $otpable->notify(new OtpNotification($otpRecord));

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to send OTP notification', [
                'otpable_type' => $otpable->getMorphClass(),
                'otpable_id' => $otpable->getKey(),
                'type' => $otpRecord->type,
                'destination' => $otpRecord->destination,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Find a valid (non-expired, non-verified, non-used, non-cancelled) OTP.
     */
    private function findValidOtp(Model $otpable, string $type, string $destination): ?OneTimePassword
    {
        return OneTimePassword::where('otpable_type', $otpable->getMorphClass())
            ->where('otpable_id', $otpable->getKey())
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
     * Find a pending (valid) OTP for resend operations.
     */
    private function findPendingOtp(Model $otpable, string $type, string $destination): ?OneTimePassword
    {
        return OneTimePassword::where('otpable_type', $otpable->getMorphClass())
            ->where('otpable_id', $otpable->getKey())
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
     * Delete old pending OTPs when creating a new one.
     */
    private function deleteOldPendingOtps(Model $otpable, string $type, string $destination): void
    {
        OneTimePassword::where('otpable_type', $otpable->getMorphClass())
            ->where('otpable_id', $otpable->getKey())
            ->where('type', $type)
            ->where('destination', $destination)
            ->whereNull('verified_at')
            ->whereNull('used_at')
            ->delete();
    }

    /**
     * Build a rate limit key for OTP requests.
     */
    private function buildRequestRateLimitKey(Model $otpable, string $type, string $destination): string
    {
        return sprintf(
            'otp_request:%s:%d:%s:%s',
            $otpable->getMorphClass(),
            $otpable->getKey(),
            $type,
            md5($destination)
        );
    }

    /**
     * Build a rate limit key for OTP verifications.
     */
    private function buildVerificationRateLimitKey(Model $otpable, string $type, string $destination): string
    {
        return sprintf(
            'otp_verify:%s:%d:%s:%s',
            $otpable->getMorphClass(),
            $otpable->getKey(),
            $type,
            md5($destination)
        );
    }

    /**
     * Check if a rate limit has been exceeded for a given key.
     */
    private function isRateLimitExceeded(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Get the number of seconds until the rate limit resets.
     */
    private function getAvailableInSeconds(string $key): int
    {
        return RateLimiter::availableIn($key);
    }
}
