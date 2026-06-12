<?php

// src/Otp/Contexts/OtpProcessingContext.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Contexts;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\Mfa\Collections\OtpAttemptRecordCollection;
use AndyDefer\Mfa\Otp\Enums\OtpProcessingStep;
use AndyDefer\Mfa\Otp\Models\OneTimePassword;
use AndyDefer\Mfa\Records\OtpAttemptRecord;
use AndyDefer\Mfa\Records\OtpResultRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class OtpProcessingContext
{
    private OtpProcessingStep $current_step;

    private ?OneTimePassword $otp_record = null;

    private ?string $plain_code = null;

    private int $attempts = 0;

    private bool $is_verified = false;

    private bool $is_consumed = false;

    private ?string $error = null;

    private OtpAttemptRecordCollection $attempt_history;

    private ?OtpResultRecord $final_result = null;

    private StringTypedCollection $metadata;

    public function __construct(
        private readonly string $type,
        private readonly string $destination,
    ) {
        $this->current_step = OtpProcessingStep::START;
        $this->attempt_history = new OtpAttemptRecordCollection;
        $this->metadata = new StringTypedCollection;
    }

    // Getters
    public function getType(): string
    {
        return $this->type;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function getCurrentStep(): OtpProcessingStep
    {
        return $this->current_step;
    }

    public function getOtpRecord(): ?OneTimePassword
    {
        return $this->otp_record;
    }

    public function getPlainCode(): ?string
    {
        return $this->plain_code;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    public function isConsumed(): bool
    {
        return $this->is_consumed;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getAttemptHistory(): OtpAttemptRecordCollection
    {
        return $this->attempt_history;
    }

    public function getFinalResult(): ?OtpResultRecord
    {
        return $this->final_result;
    }

    public function getMetadata(): StringTypedCollection
    {
        return $this->metadata;
    }

    // Setters
    public function setCurrentStep(OtpProcessingStep $step): void
    {
        $this->current_step = $step;
    }

    public function setOtpRecord(OneTimePassword $otpRecord, string $plainCode): void
    {
        $this->otp_record = $otpRecord;
        $this->plain_code = $plainCode;
        $this->current_step = OtpProcessingStep::OTP_CREATED;
    }

    public function recordAttempt(string $code, bool $success, ?string $errorMessage = null): void
    {
        $this->attempts++;
        $this->attempt_history->add(new OtpAttemptRecord(
            attempt_number: $this->attempts,
            code_provided: $code,
            success: $success,
            error_message: $errorMessage,
            attempted_at: new DateTimeVO(null),
        ));
    }

    public function markAsVerified(): void
    {
        $this->is_verified = true;
        $this->current_step = OtpProcessingStep::VERIFIED;
    }

    public function markAsConsumed(): void
    {
        $this->is_consumed = true;
        $this->current_step = OtpProcessingStep::CONSUMED;
    }

    public function setError(string $error): void
    {
        $this->error = $error;
        $this->current_step = OtpProcessingStep::FAILED;
    }

    public function setFinalResult(OtpResultRecord $result): void
    {
        $this->final_result = $result;
        $this->current_step = $result->is_success ? OtpProcessingStep::COMPLETED : OtpProcessingStep::FAILED;
    }

    public function addMetadata(string $key, string $value): void
    {
        $this->metadata->add("{$key}:{$value}");
    }

    // Méthodes de question
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function isCompleted(): bool
    {
        return $this->current_step === OtpProcessingStep::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->current_step === OtpProcessingStep::FAILED;
    }

    public function canRetry(): bool
    {
        return ! $this->is_verified && ! $this->is_consumed && $this->attempts < 3;
    }
}
