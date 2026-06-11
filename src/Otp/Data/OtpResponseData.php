<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Data;

use AndyDefer\Mfa\Otp\Enums\ErrorCode;
use AndyDefer\Mfa\Otp\Enums\OtpStatus;

/**
 * Immutable DTO for OTP operation responses.
 *
 * Provides a standardized response structure for all OTP operations
 * (send, verify, resend, cancel) with factory methods for each possible outcome.
 * This ensures consistent error handling and response formatting throughout the package.
 */
final class OtpResponseData
{
    /**
     * Private constructor - use factory methods instead.
     *
     * @param  OtpStatus  $status  Operation status enum
     * @param  ErrorCode|null  $errorCode  Optional error code for failed operations
     * @param  string|null  $message  Human-readable message (will be in French for user-facing responses)
     * @param  array|null  $data  Optional additional data (e.g., remaining attempts, expiry timestamp)
     */
    private function __construct(
        public readonly OtpStatus $status,
        public readonly ?ErrorCode $errorCode = null,
        public readonly ?string $message = null,
        public readonly ?array $data = null
    ) {}

    /**
     * Create a success response.
     *
     * @param  array|null  $data  Optional additional data (e.g., verification token, user info)
     * @param  string|null  $message  Optional custom success message (defaults to French message)
     */
    public static function success(?array $data = null, ?string $message = null): self
    {
        return new self(
            status: OtpStatus::SUCCESS,
            message: $message ?? 'Opération réussie.',
            data: $data
        );
    }

    /**
     * Create a generic failure response.
     *
     * @param  ErrorCode  $errorCode  Specific error code identifying the failure type
     * @param  string|null  $message  Optional custom error message
     * @param  array|null  $data  Optional additional error context
     */
    public static function failed(ErrorCode $errorCode, ?string $message = null, ?array $data = null): self
    {
        return new self(
            status: OtpStatus::FAILED,
            errorCode: $errorCode,
            message: $message ?? $errorCode->message(),
            data: $data
        );
    }

    /**
     * Create a rate-limited response when too many requests.
     *
     * @param  string  $message  Human-readable message explaining the rate limit
     * @param  array|null  $data  Optional additional data (e.g., retry_after_seconds)
     */
    public static function rateLimited(string $message, ?array $data = null): self
    {
        return new self(
            status: OtpStatus::RATE_LIMITED,
            errorCode: ErrorCode::RATE_LIMIT_EXCEEDED,
            message: $message,
            data: $data
        );
    }

    /**
     * Create an invalid code response for verification failures.
     *
     * @param  string  $message  Human-readable error message
     * @param  array|null  $data  Optional additional data (e.g., remaining_attempts)
     */
    public static function invalidCode(string $message, ?array $data = null): self
    {
        return new self(
            status: OtpStatus::INVALID_CODE,
            errorCode: ErrorCode::INVALID_OTP,
            message: $message,
            data: $data
        );
    }

    /**
     * Create an expired code response.
     *
     * @param  string  $message  Human-readable error message
     * @param  array|null  $data  Optional additional data (e.g., expired_at timestamp)
     */
    public static function expiredCode(string $message, ?array $data = null): self
    {
        return new self(
            status: OtpStatus::EXPIRED_CODE,
            errorCode: ErrorCode::OTP_EXPIRED,
            message: $message,
            data: $data
        );
    }

    /**
     * Create a response for when max verification attempts are exceeded.
     *
     * @param  string  $message  Human-readable error message
     * @param  array|null  $data  Optional additional data (e.g., max_attempts)
     */
    public static function maxAttemptsExceeded(string $message, ?array $data = null): self
    {
        return new self(
            status: OtpStatus::MAX_ATTEMPTS_EXCEEDED,
            errorCode: ErrorCode::MAX_ATTEMPTS_EXCEEDED,
            message: $message,
            data: $data
        );
    }

    /**
     * Create a response when OTP record is not found.
     *
     * @param  string  $message  Human-readable error message
     * @param  array|null  $data  Optional additional context
     */
    public static function notFound(string $message, ?array $data = null): self
    {
        return new self(
            status: OtpStatus::NOT_FOUND,
            errorCode: ErrorCode::OTP_NOT_FOUND,
            message: $message,
            data: $data
        );
    }

    /**
     * Create a response when OTP sending fails.
     *
     * @param  string  $message  Human-readable error message
     * @param  array|null  $data  Optional additional error context (e.g., channel that failed)
     */
    public static function sendFailed(string $message, ?array $data = null): self
    {
        return new self(
            status: OtpStatus::SEND_FAILED,
            errorCode: ErrorCode::OTP_SEND_FAILED,
            message: $message,
            data: $data
        );
    }

    /**
     * Create a response when OTP resending fails.
     *
     * @param  string  $message  Human-readable error message
     * @param  array|null  $data  Optional additional error context
     */
    public static function resendFailed(string $message, ?array $data = null): self
    {
        return new self(
            status: OtpStatus::RESEND_FAILED,
            errorCode: ErrorCode::OTP_RESEND_FAILED,
            message: $message,
            data: $data
        );
    }

    /**
     * Check if the operation was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === OtpStatus::SUCCESS;
    }

    /**
     * Check if the operation failed (any non-success status).
     */
    public function isFailed(): bool
    {
        return ! $this->isSuccess();
    }

    /**
     * Convert the response to a standardized array format.
     *
     * This format is suitable for JSON responses in API contexts.
     *
     * @return array<string, mixed> Array with keys: success, status, error_code, message, data
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccess(),
            'status' => $this->status->value,
            'error_code' => $this->errorCode?->value,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
