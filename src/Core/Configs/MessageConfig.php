<?php

// src/Core/Configs/MessageConfig.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Core\Configs;

use AndyDefer\Mfa\Core\Contracts\Configs\MessageConfigInterface;

final class MessageConfig implements MessageConfigInterface
{
    // Notification email messages
    public function subject(): string
    {
        return config('mfa.messages.subject', 'Your verification code - :app_name');
    }

    public function greeting(): string
    {
        return config('mfa.messages.greeting', 'Hello %s!');
    }

    public function intro(): string
    {
        return config('mfa.messages.intro', 'Please use the verification code below:');
    }

    public function expiresIn(): string
    {
        return config('mfa.messages.expires_in', 'This code will expire in :minutes minute(s).');
    }

    public function ignoreRequest(): string
    {
        return config('mfa.messages.ignore_request', 'If you did not request this verification, please ignore this email.');
    }

    public function salutation(): string
    {
        return config('mfa.messages.salutation', "Sincerely,\n:app_name");
    }

    public function defaultUserName(): string
    {
        return config('mfa.messages.default_user_name', 'User');
    }

    // Success messages
    public function sendSuccess(): string
    {
        return config('mfa.messages.send_success', 'Verification code sent successfully.');
    }

    public function resendSuccess(): string
    {
        return config('mfa.messages.resend_success', 'Verification code resent successfully.');
    }

    public function verifySuccess(): string
    {
        return config('mfa.messages.verify_success', 'OTP verified successfully.');
    }

    public function cancelSuccess(): string
    {
        return config('mfa.messages.cancel_success', ':count OTP(s) cancelled successfully.');
    }

    public function noPendingToCancel(): string
    {
        return config('mfa.messages.no_pending_to_cancel', 'No pending OTPs found to cancel.');
    }

    // Error messages
    public function sendFailed(): string
    {
        return config('mfa.messages.send_failed', 'Unable to send OTP. Please try again.');
    }

    public function resendFailed(): string
    {
        return config('mfa.messages.resend_failed', 'Unable to resend OTP. Please try again.');
    }

    public function otpNotFound(): string
    {
        return config('mfa.messages.otp_not_found', 'Invalid or expired OTP code.');
    }

    public function expiredCode(): string
    {
        return config('mfa.messages.expired_code', 'OTP code has expired. Please request a new one.');
    }

    public function maxAttemptsExceeded(): string
    {
        return config('mfa.messages.max_attempts_exceeded', 'Maximum verification attempts exceeded. Please request a new OTP.');
    }

    public function invalidCodeAttemptsRemaining(): string
    {
        return config('mfa.messages.invalid_code_attempts_remaining', 'Invalid OTP code. You have :attempts attempts remaining.');
    }

    public function invalidCodeOneAttemptRemaining(): string
    {
        return config('mfa.messages.invalid_code_one_attempt_remaining', 'Invalid OTP code. You have 1 attempt remaining.');
    }

    public function rateLimited(): string
    {
        return config('mfa.messages.rate_limited', 'Please wait :seconds seconds before requesting another OTP.');
    }
}
