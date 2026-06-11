<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Enums;

/**
 * Status codes for OTP operation responses.
 *
 * Represents all possible outcomes of an OTP operation
 * (send, verify, resend, cancel). These statuses are used
 * in OtpResponseData to provide consistent response handling
 * throughout the package.
 */
enum OtpStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case RATE_LIMITED = 'rate_limited';
    case INVALID_CODE = 'invalid_code';
    case EXPIRED_CODE = 'expired_code';
    case MAX_ATTEMPTS_EXCEEDED = 'max_attempts_exceeded';
    case NOT_FOUND = 'not_found';
    case SEND_FAILED = 'send_failed';
    case RESEND_FAILED = 'resend_failed';
}
