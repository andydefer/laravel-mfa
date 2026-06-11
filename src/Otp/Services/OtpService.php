<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use AndyDefer\Mfa\Core\Helpers\TranslationHelper;
use AndyDefer\Mfa\Otp\Contracts\CodeGeneratorInterface;
use AndyDefer\Mfa\Otp\Contracts\RateLimiterInterface;
use AndyDefer\Mfa\Otp\Data\OtpResponseData;
use AndyDefer\Mfa\Otp\Models\OneTimePassword;
use AndyDefer\Mfa\Otp\Notifications\OtpNotification;

/**
 * Core service for One-Time Password (OTP) operations.
 *
 * Handles the complete OTP lifecycle including generation, sending,
 * verification, resending, and cancellation. Integrates with rate limiting
 * to prevent abuse and brute-force attacks.
 */
class OtpService
{
    /**
     * Create a new OTP service instance.
     *
     * @param  CodeGeneratorInterface  $codeGenerator  Generator for OTP codes
     * @param  RateLimiterInterface  $rateLimiter  Rate limiter for abuse prevention
     * @param  int  $defaultExpiryMinutes  Default OTP lifetime in minutes
     * @param  int  $defaultMaxAttempts  Default maximum verification attempts
     * @param  int  $rateLimitRequests  Maximum requests per time window
     * @param  int  $rateLimitVerifications  Maximum verifications per time window
     * @param  int  $rateLimitDecayMinutes  Rate limit window duration in minutes
     * @param  int  $failedVerificationDecaySeconds  Decay time for failed verifications
     * @param  int  $rateLimitHitDecaySeconds  Decay time for rate limit hits
     */
    public function __construct(
        private readonly CodeGeneratorInterface $codeGenerator,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly int $defaultExpiryMinutes = 10,
        private readonly int $defaultMaxAttempts = 3,
        private readonly int $rateLimitRequests = 3,
        private readonly int $rateLimitVerifications = 5,
        private readonly int $rateLimitDecayMinutes = 60,
        private readonly int $failedVerificationDecaySeconds = 300,
        private readonly int $rateLimitHitDecaySeconds = 60
    ) {}

    /**
     * Send a new OTP to the specified destination.
     *
     * Creates an OTP record, stores it in the database, sends the notification,
     * and applies rate limiting. Previous pending OTPs are automatically deleted.
     *
     * @param  Model  $otpable  The entity requesting the OTP (User, Admin, etc.)
     * @param  string  $type  OTP type (email_verification, password_reset, 2fa, etc.)
     * @param  string  $destination  Destination address (email, phone number)
     * @param  array|null  $channels  Delivery channels to use (mail, sms, whatsapp)
     * @param  array|null  $metadata  Additional metadata (IP, user agent, etc.)
     * @param  int|null  $expiresInMinutes  Custom expiry time (uses default if null)
     * @param  int|null  $maxAttempts  Custom max attempts (uses default if null)
     * @return OtpResponseData Response containing success status and metadata
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
            return $this->createRateLimitedResponse($rateLimitKey);
        }

        $this->deleteOldPendingOtps($otpable, $type, $destination);

        $plainCode = $this->codeGenerator->generate();

        $otpRecord = $this->createOtpRecord(
            otpable: $otpable,
            type: $type,
            destination: $destination,
            channels: $channels,
            metadata: $metadata,
            expiresInMinutes: $expiresInMinutes ?? $this->defaultExpiryMinutes,
            maxAttempts: $maxAttempts ?? $this->defaultMaxAttempts,
            plainCode: $plainCode
        );

        $notificationSent = $this->sendOtpNotification($otpable, $otpRecord, $plainCode);

        if (! $notificationSent) {
            $otpRecord->delete();

            return OtpResponseData::sendFailed(TranslationHelper::trans('messages.send_failed'));
        }

        $this->recordRateLimitHit($rateLimitKey);

        return OtpResponseData::success(
            data: [
                'expires_at' => $otpRecord->expires_at->toIso8601String(),
                'expires_in_minutes' => $expiresInMinutes ?? $this->defaultExpiryMinutes,
            ],
            message: TranslationHelper::trans('messages.send_success')
        );
    }

    /**
     * Resend an OTP, cancelling any pending OTP first.
     *
     * If no pending OTP exists, falls back to sending a new one.
     * Reuses previous channels and metadata if not explicitly provided.
     *
     * @param  Model  $otpable  The entity requesting the OTP
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @param  array|null  $channels  Delivery channels (reuses previous if null)
     * @param  array|null  $metadata  Additional metadata (reuses previous if null)
     * @param  int|null  $expiresInMinutes  Custom expiry time
     * @param  int|null  $maxAttempts  Custom max attempts (reuses previous if null)
     * @return OtpResponseData Response containing success status and metadata
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

        if (! $pendingOtp) {
            return $this->send($otpable, $type, $destination, $channels, $metadata, $expiresInMinutes, $maxAttempts);
        }

        $rateLimitKey = $this->buildRequestRateLimitKey($otpable, $type, $destination);

        if ($this->isRateLimitExceeded($rateLimitKey, $this->rateLimitRequests)) {
            return $this->createRateLimitedResponse($rateLimitKey);
        }

        $plainCode = $this->codeGenerator->generate();

        $newOtpRecord = $this->createOtpRecord(
            otpable: $otpable,
            type: $type,
            destination: $destination,
            channels: $channels ?? $pendingOtp->channels,
            metadata: $metadata ?? $pendingOtp->meta,
            expiresInMinutes: $expiresInMinutes ?? $this->defaultExpiryMinutes,
            maxAttempts: $maxAttempts ?? $pendingOtp->max_attempts,
            plainCode: $plainCode
        );

        $pendingOtp->markAsCancelled();

        $notificationSent = $this->sendOtpNotification($otpable, $newOtpRecord, $plainCode);

        if (! $notificationSent) {
            $newOtpRecord->delete();

            return OtpResponseData::resendFailed(TranslationHelper::trans('messages.resend_failed'));
        }

        $this->recordRateLimitHit($rateLimitKey);

        return OtpResponseData::success(
            data: [
                'expires_at' => $newOtpRecord->expires_at->toIso8601String(),
                'expires_in_minutes' => $expiresInMinutes ?? $this->defaultExpiryMinutes,
            ],
            message: TranslationHelper::trans('messages.resend_success')
        );
    }

    /**
     * Verify an OTP code provided by the user.
     *
     * Checks rate limiting, OTP existence, expiration, attempts limit,
     * and code validity. Marks the OTP as verified and optionally consumed.
     *
     * @param  Model  $otpable  The entity attempting verification
     * @param  string  $code  The OTP code provided by the user
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @param  bool  $consume  Whether to mark the OTP as used after verification
     * @return OtpResponseData Response with verification status
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
            return $this->createRateLimitedResponse($rateLimitKey);
        }

        // Find OTP without expiration filter first
        $otpRecord = $this->findOtpForVerification($otpable, $type, $destination);

        if (! $otpRecord) {
            $this->recordFailedVerificationAttempt($rateLimitKey);

            return OtpResponseData::notFound(TranslationHelper::trans('messages.otp_not_found'));
        }

        // Check expiration first
        if ($otpRecord->isExpired()) {
            $otpRecord->markAsCancelled();
            $this->recordFailedVerificationAttempt($rateLimitKey);

            return OtpResponseData::expiredCode(TranslationHelper::trans('messages.expired_code'));
        }

        if ($otpRecord->isUsed() || $otpRecord->isVerified()) {
            $this->recordFailedVerificationAttempt($rateLimitKey);

            return OtpResponseData::notFound(TranslationHelper::trans('messages.otp_not_found'));
        }

        if (! $otpRecord->verifyCode($code)) {
            return $this->handleFailedVerification($otpRecord, $rateLimitKey);
        }

        return $this->handleSuccessfulVerification($otpRecord, $rateLimitKey, $consume, $otpable, $type, $destination);
    }

    /**
     * Find an OTP for verification (including expired ones).
     *
     * @param  Model  $otpable  The entity
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @return OneTimePassword|null The OTP or null
     */
    private function findOtpForVerification(Model $otpable, string $type, string $destination): ?OneTimePassword
    {
        return OneTimePassword::where('otpable_type', $otpable->getMorphClass())
            ->where('otpable_id', $otpable->getKey())
            ->where('type', $type)
            ->where('destination', $destination)
            ->whereNull('verified_at')
            ->whereNull('used_at')
            ->whereNull('cancelled_at')
            ->latest()
            ->first();
    }

    /**
     * Cancel all pending OTPs for a given entity, type, and destination.
     *
     * @param  Model  $otpable  The entity whose OTPs should be cancelled
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @return OtpResponseData Response with count of cancelled OTPs
     */
    public function cancel(Model $otpable, string $type, string $destination): OtpResponseData
    {
        $cancelledCount = $otpable->cancelOtps($type, $destination);

        $message = $cancelledCount > 0
            ? TranslationHelper::trans('messages.cancel_success', ['count' => $cancelledCount])
            : TranslationHelper::trans('messages.no_pending_to_cancel');

        return OtpResponseData::success(
            data: ['cancelled_count' => $cancelledCount],
            message: $message
        );
    }

    /**
     * Hash a plain text code for secure storage.
     *
     * @param  string  $plainCode  The plain code to hash
     * @return string Hashed code
     */
    private function hashPlainCode(string $plainCode): string
    {
        return Hash::make($plainCode);
    }

    /**
     * Check if a rate limit has been exceeded for a given key.
     *
     * @param  string  $key  Rate limit key
     * @param  int  $limit  Maximum allowed attempts
     * @return bool True if rate limit exceeded
     */
    private function isRateLimitExceeded(string $key, int $limit): bool
    {
        return $this->rateLimiter->isExceeded($key, $limit);
    }

    /**
     * Record a hit on the rate limiter for a given key.
     *
     * @param  string  $key  Rate limit key
     */
    private function recordRateLimitHit(string $key): void
    {
        $this->rateLimiter->hit($key, $this->rateLimitHitDecaySeconds);
    }

    /**
     * Record a failed verification attempt on the rate limiter.
     *
     * @param  string  $key  Rate limit key
     */
    private function recordFailedVerificationAttempt(string $key): void
    {
        $this->rateLimiter->hit($key, $this->failedVerificationDecaySeconds);
    }

    /**
     * Create a rate limited response with appropriate wait time.
     *
     * @param  string  $key  Rate limit key
     * @return OtpResponseData Rate limited response
     */
    private function createRateLimitedResponse(string $key): OtpResponseData
    {
        $waitSeconds = $this->rateLimiter->getAvailableInSeconds($key);

        return OtpResponseData::rateLimited(
            TranslationHelper::trans('messages.rate_limited', ['seconds' => $waitSeconds])
        );
    }

    /**
     * Handle a failed verification attempt.
     *
     * @param  OneTimePassword  $otpRecord  The OTP record
     * @param  string  $rateLimitKey  Rate limit key
     * @return OtpResponseData Response based on attempt count
     */
    private function handleFailedVerification(OneTimePassword $otpRecord, string $rateLimitKey): OtpResponseData
    {
        $otpRecord->incrementAttempts();
        $this->recordFailedVerificationAttempt($rateLimitKey);

        $remainingAttempts = $otpRecord->max_attempts - $otpRecord->attempts;

        if ($otpRecord->hasExceededMaxAttempts()) {
            $otpRecord->markAsCancelled();

            return OtpResponseData::maxAttemptsExceeded(
                TranslationHelper::trans('messages.max_attempts_exceeded')
            );
        }

        $message = $remainingAttempts > 1
            ? TranslationHelper::trans('messages.invalid_code_attempts_remaining', ['attempts' => $remainingAttempts])
            : TranslationHelper::trans('messages.invalid_code_one_attempt_remaining');

        return OtpResponseData::invalidCode($message);
    }

    /**
     * Handle a successful verification attempt.
     *
     * @param  OneTimePassword  $otpRecord  The OTP record
     * @param  string  $rateLimitKey  Rate limit key for verification
     * @param  bool  $consume  Whether to mark as used
     * @param  Model  $otpable  The entity being verified
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @return OtpResponseData Success response
     */
    private function handleSuccessfulVerification(
        OneTimePassword $otpRecord,
        string $rateLimitKey,
        bool $consume,
        Model $otpable,
        string $type,
        string $destination
    ): OtpResponseData {
        $otpRecord->markAsVerified();

        if ($consume) {
            $otpRecord->markAsUsed();
        }

        $this->rateLimiter->clear($rateLimitKey);
        $this->rateLimiter->clear($this->buildRequestRateLimitKey($otpable, $type, $destination));

        return OtpResponseData::success(
            data: ['meta' => $otpRecord->meta],
            message: TranslationHelper::trans('messages.verify_success')
        );
    }

    /**
     * Create a new OTP record in the database.
     *
     * @param  Model  $otpable  The entity requesting the OTP
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @param  array|null  $channels  Delivery channels
     * @param  array|null  $metadata  Additional metadata
     * @param  int  $expiresInMinutes  Expiry time in minutes
     * @param  int  $maxAttempts  Maximum verification attempts
     * @param  string  $plainCode  Plain code to hash and store
     * @return OneTimePassword The created OTP record
     */
    private function createOtpRecord(
        Model $otpable,
        string $type,
        string $destination,
        ?array $channels,
        ?array $metadata,
        int $expiresInMinutes,
        int $maxAttempts,
        string $plainCode
    ): OneTimePassword {
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
     * Send the OTP notification to the notifiable entity.
     *
     * @param  Model  $otpable  The entity receiving the notification
     * @param  OneTimePassword  $otpRecord  The OTP record
     * @param  string  $plainCode  The plain code to include in notification
     * @return bool True if notification was sent successfully
     */
    private function sendOtpNotification(Model $otpable, OneTimePassword $otpRecord, string $plainCode): bool
    {
        try {
            $otpable->notify(new OtpNotification($otpRecord, $plainCode));

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
     * Find any OTP record (valid or invalid) for the given parameters.
     *
     * @param  Model  $otpable  The entity
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @return OneTimePassword|null The most recent OTP or null
     */
    private function findOtp(Model $otpable, string $type, string $destination): ?OneTimePassword
    {
        return OneTimePassword::where('otpable_type', $otpable->getMorphClass())
            ->where('otpable_id', $otpable->getKey())
            ->where('type', $type)
            ->where('destination', $destination)
            ->whereNull('cancelled_at')
            ->latest()
            ->first();
    }

    /**
     * Find a valid OTP that is not expired, verified, used, or cancelled.
     *
     * @param  Model  $otpable  The entity
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @return OneTimePassword|null The valid OTP or null
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
     * Find a pending OTP that is still valid for use.
     *
     * @param  Model  $otpable  The entity
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @return OneTimePassword|null The pending OTP or null
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
     * Delete all old pending OTPs for the given parameters.
     *
     * @param  Model  $otpable  The entity
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
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
     * Build a rate limit key for OTP request operations.
     *
     * @param  Model  $otpable  The entity
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @return string Rate limit key
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
     * Build a rate limit key for OTP verification operations.
     *
     * @param  Model  $otpable  The entity
     * @param  string  $type  OTP type
     * @param  string  $destination  Destination address
     * @return string Rate limit key
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
}
