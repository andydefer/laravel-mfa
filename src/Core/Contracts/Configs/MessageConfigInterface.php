<?php

// src/Core/Contracts/Configs/MessageConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Core\Contracts\Configs;

interface MessageConfigInterface
{
    // Notification email messages
    public function subject(): string;

    public function greeting(): string;

    public function intro(): string;

    public function expiresIn(): string;

    public function ignoreRequest(): string;

    public function salutation(): string;

    public function defaultUserName(): string;

    // Success messages
    public function sendSuccess(): string;

    public function resendSuccess(): string;

    public function verifySuccess(): string;

    public function cancelSuccess(): string;

    public function noPendingToCancel(): string;

    // Error messages
    public function sendFailed(): string;

    public function resendFailed(): string;

    public function otpNotFound(): string;

    public function expiredCode(): string;

    public function maxAttemptsExceeded(): string;

    public function invalidCodeAttemptsRemaining(): string;

    public function invalidCodeOneAttemptRemaining(): string;

    public function rateLimited(): string;
}
