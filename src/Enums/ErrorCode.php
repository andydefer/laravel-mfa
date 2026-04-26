<?php

declare(strict_types=1);

namespace Kani\Otp\Enums;

/**
 * Standardized error codes for OTP operations.
 *
 * Each error case in the OTP lifecycle has a corresponding code that can be
 * used for consistent error handling, API responses, and logging throughout
 * the application. The enum provides human-readable messages and appropriate
 * HTTP status codes for each error scenario.
 */
enum ErrorCode: string
{
    case RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    case OTP_NOT_FOUND = 'otp_not_found';
    case INVALID_OTP = 'invalid_otp';
    case MAX_ATTEMPTS_EXCEEDED = 'max_attempts_exceeded';
    case OTP_SEND_FAILED = 'otp_send_failed';
    case OTP_RESEND_FAILED = 'otp_resend_failed';
    case OTP_EXPIRED = 'otp_expired';

    /**
     * Get a human-readable error message for this error code.
     *
     * Messages are in French for user-facing responses.
     */
    public function message(): string
    {
        return match ($this) {
            self::RATE_LIMIT_EXCEEDED => 'Trop de tentatives. Veuillez réessayer plus tard.',
            self::OTP_NOT_FOUND => 'Code OTP introuvable ou déjà utilisé.',
            self::INVALID_OTP => 'Le code OTP fourni est invalide.',
            self::MAX_ATTEMPTS_EXCEEDED => 'Nombre maximum de tentatives de vérification dépassé.',
            self::OTP_SEND_FAILED => 'Échec de l\'envoi du code OTP.',
            self::OTP_RESEND_FAILED => 'Échec du renvoi du code OTP.',
            self::OTP_EXPIRED => 'Le code OTP a expiré.',
        };
    }

    /**
     * Get the appropriate HTTP status code for this error.
     *
     * @return int HTTP status code (4xx for client errors, 5xx for server errors)
     */
    public function httpStatusCode(): int
    {
        return match ($this) {
            self::RATE_LIMIT_EXCEEDED => 429,
            self::OTP_NOT_FOUND => 404,
            self::INVALID_OTP => 422,
            self::MAX_ATTEMPTS_EXCEEDED => 422,
            self::OTP_SEND_FAILED => 500,
            self::OTP_RESEND_FAILED => 500,
            self::OTP_EXPIRED => 422,
        };
    }
}
