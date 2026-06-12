<?php

// src/Otp/Enums/OtpProcessingStep.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Enums;

enum OtpProcessingStep: string
{
    case START = 'start';
    case SENDING = 'sending';
    case SENT = 'sent';
    case RESENDING = 'resending';
    case RESENT = 'resent';
    case VERIFYING = 'verifying';
    case VERIFIED = 'verified';
    case CONSUMED = 'consumed';
    case OTP_CREATED = 'otp_created';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::CANCELLED => true,
            default => false,
        };
    }

    public function isSuccess(): bool
    {
        return match ($this) {
            self::COMPLETED, self::VERIFIED, self::CONSUMED, self::SENT, self::RESENT => true,
            default => false,
        };
    }

    public function isError(): bool
    {
        return $this === self::FAILED;
    }
}
