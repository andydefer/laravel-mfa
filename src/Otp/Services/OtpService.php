<?php
// src/Otp/Services/OtpService.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Mfa\Configs\MfaConfig;
use AndyDefer\Mfa\Core\Contracts\Configs\MessageConfigInterface;
use AndyDefer\Mfa\Core\Services\TranslationService;
use AndyDefer\Mfa\Otp\Contracts\CodeGeneratorInterface;
use AndyDefer\Mfa\Otp\Contracts\RateLimiterInterface;
use AndyDefer\Mfa\Otp\Contexts\OtpProcessingContext;
use AndyDefer\Mfa\Otp\Enums\OtpProcessingStep;
use AndyDefer\Mfa\Otp\Models\OneTimePassword;
use AndyDefer\Mfa\Otp\Notifications\OtpNotification;
use AndyDefer\Mfa\Otp\Repositories\OneTimePasswordRepository;
use AndyDefer\Mfa\Records\OtpResultRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

class OtpService
{
    private HydrationService $hydration;

    public function __construct(
        private readonly CodeGeneratorInterface $codeGenerator,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly TranslationService $translator,
        private readonly MessageConfigInterface $messageConfig,
        private readonly HashManager $hash,
        private readonly OneTimePasswordRepository $otpRepository,
        private readonly MfaConfig $config,
    ) {
        $this->hydration = new HydrationService();
    }

    public function send(
        Model $otpable,
        OtpProcessingContext $context,
        ?array $channels = null,
        ?array $metadata = null,
    ): OtpProcessingContext {
        $context->setCurrentStep(OtpProcessingStep::SENDING);

        $rateLimitKey = $this->buildRequestRateLimitKey($otpable, $context);

        if ($this->isRateLimitExceeded($rateLimitKey, $this->config->otpSecurityConfig()->rate_limit_requests)) {
            $context->setError($this->translator->trans($this->messageConfig->rateLimited(), ['seconds' => 60]));
            $context->setFinalResult(new OtpResultRecord(
                is_success: false,
                message: $this->translator->trans($this->messageConfig->rateLimited(), ['seconds' => 60]),
                data: null,
                occurred_at: new DateTimeVO(null),
            ));
            return $context;
        }

        $this->deleteOldPendingOtps($otpable, $context);

        $plainCode = $this->codeGenerator->generate();
        $expiryMinutes = $this->config->otpConfig()->default_expiry_minutes;
        $maxAttempts = $this->config->otpConfig()->default_max_attempts;

        $otpModel = $this->createOtpModel(
            otpable: $otpable,
            context: $context,
            channels: $channels,
            metadata: $metadata,
            expiresInMinutes: $expiryMinutes,
            maxAttempts: $maxAttempts,
            plainCode: $plainCode
        );

        $context->setOtpRecord($otpModel, $plainCode);
        $context->addMetadata('expires_in_minutes', (string) $expiryMinutes);
        $context->addMetadata('expires_at', $otpModel->expires_at?->toIso8601String() ?? '');

        $notificationSent = $this->sendOtpNotification($otpable, $otpModel, $plainCode);

        if (!$notificationSent) {
            $this->otpRepository->delete($otpModel->id);
            $context->setError($this->translator->trans($this->messageConfig->sendFailed()));
            $context->setFinalResult(new OtpResultRecord(
                is_success: false,
                message: $this->translator->trans($this->messageConfig->sendFailed()),
                data: null,
                occurred_at: new DateTimeVO(null),
            ));
            return $context;
        }

        $this->recordRateLimitHit($rateLimitKey);
        $context->setFinalResult(new OtpResultRecord(
            is_success: true,
            message: $this->translator->trans($this->messageConfig->sendSuccess()),
            data: new StrictDataObject([
                'expires_at' => $otpModel->expires_at?->toIso8601String(),
                'expires_in_minutes' => $expiryMinutes,
            ]),
            occurred_at: new DateTimeVO(null),
        ));
        $context->setCurrentStep(OtpProcessingStep::SENT);

        return $context;
    }

    public function resend(
        Model $otpable,
        OtpProcessingContext $context,
        ?array $channels = null,
        ?array $metadata = null,
    ): OtpProcessingContext {
        $context->setCurrentStep(OtpProcessingStep::RESENDING);

        $pendingOtpModel = $this->findPendingOtp($otpable, $context);

        if (!$pendingOtpModel) {
            return $this->send($otpable, $context, $channels, $metadata);
        }

        $rateLimitKey = $this->buildRequestRateLimitKey($otpable, $context);

        if ($this->isRateLimitExceeded($rateLimitKey, $this->config->otpSecurityConfig()->rate_limit_requests)) {
            $context->setError($this->translator->trans($this->messageConfig->rateLimited(), ['seconds' => 60]));
            $context->setFinalResult(new OtpResultRecord(
                is_success: false,
                message: $this->translator->trans($this->messageConfig->rateLimited(), ['seconds' => 60]),
                data: null,
                occurred_at: new DateTimeVO(null),
            ));
            return $context;
        }

        $plainCode = $this->codeGenerator->generate();
        $expiryMinutes = $this->config->otpConfig()->default_expiry_minutes;

        $channelsToUse = $channels ?? $pendingOtpModel->channels;
        $metadataToUse = $metadata ?? ($pendingOtpModel->meta ?? null);
        $maxAttemptsToUse = $pendingOtpModel->max_attempts ?? $this->config->otpConfig()->default_max_attempts;

        $this->cancelOtpModel($pendingOtpModel);
        $pendingOtpModel->refresh();

        $newOtpModel = $this->createOtpModel(
            otpable: $otpable,
            context: $context,
            channels: $channelsToUse,
            metadata: $metadataToUse,
            expiresInMinutes: $expiryMinutes,
            maxAttempts: $maxAttemptsToUse,
            plainCode: $plainCode
        );

        $context->setOtpRecord($newOtpModel, $plainCode);

        $notificationSent = $this->sendOtpNotification($otpable, $newOtpModel, $plainCode);

        if (!$notificationSent) {
            $this->otpRepository->delete($newOtpModel->id);
            $context->setError($this->translator->trans($this->messageConfig->resendFailed()));
            $context->setFinalResult(new OtpResultRecord(
                is_success: false,
                message: $this->translator->trans($this->messageConfig->resendFailed()),
                data: null,
                occurred_at: new DateTimeVO(null),
            ));
            return $context;
        }

        $this->recordRateLimitHit($rateLimitKey);
        $context->setFinalResult(new OtpResultRecord(
            is_success: true,
            message: $this->translator->trans($this->messageConfig->resendSuccess()),
            data: new StrictDataObject([
                'expires_at' => $newOtpModel->expires_at?->toIso8601String(),
                'expires_in_minutes' => $expiryMinutes,
            ]),
            occurred_at: new DateTimeVO(null),
        ));
        $context->setCurrentStep(OtpProcessingStep::RESENT);

        return $context;
    }

    public function verify(
        Model $otpable,
        string $code,
        OtpProcessingContext $context,
        bool $consume = true,
    ): OtpProcessingContext {
        $context->setCurrentStep(OtpProcessingStep::VERIFYING);
        $context->recordAttempt($code, false);

        $rateLimitKey = $this->buildVerificationRateLimitKey($otpable, $context);

        if ($this->isRateLimitExceeded($rateLimitKey, $this->config->otpSecurityConfig()->rate_limit_verifications)) {
            $context->setError($this->translator->trans($this->messageConfig->rateLimited(), ['seconds' => 60]));
            $context->setFinalResult(new OtpResultRecord(
                is_success: false,
                message: $this->translator->trans($this->messageConfig->rateLimited(), ['seconds' => 60]),
                data: null,
                occurred_at: new DateTimeVO(null),
            ));
            return $context;
        }

        $otpModel = $this->findOtpForVerification($otpable, $context);

        if (!$otpModel) {
            $this->recordFailedVerificationAttempt($rateLimitKey);
            $context->recordAttempt($code, false, $this->translator->trans($this->messageConfig->otpNotFound()));
            $context->setError($this->translator->trans($this->messageConfig->otpNotFound()));
            $context->setFinalResult(new OtpResultRecord(
                is_success: false,
                message: $this->translator->trans($this->messageConfig->otpNotFound()),
                data: null,
                occurred_at: new DateTimeVO(null),
            ));
            return $context;
        }

        if ($otpModel->isExpired()) {
            $this->cancelOtpModel($otpModel);
            $this->recordFailedVerificationAttempt($rateLimitKey);
            $context->recordAttempt($code, false, $this->translator->trans($this->messageConfig->expiredCode()));
            $context->setError($this->translator->trans($this->messageConfig->expiredCode()));
            $context->setFinalResult(new OtpResultRecord(
                is_success: false,
                message: $this->translator->trans($this->messageConfig->expiredCode()),
                data: null,
                occurred_at: new DateTimeVO(null),
            ));
            return $context;
        }

        if ($otpModel->isUsed()) {
            $this->recordFailedVerificationAttempt($rateLimitKey);
            $context->recordAttempt($code, false, $this->translator->trans($this->messageConfig->otpNotFound()));
            $context->setError($this->translator->trans($this->messageConfig->otpNotFound()));
            $context->setFinalResult(new OtpResultRecord(
                is_success: false,
                message: $this->translator->trans($this->messageConfig->otpNotFound()),
                data: null,
                occurred_at: new DateTimeVO(null),
            ));
            return $context;
        }

        if ($otpModel->isVerified()) {
            if (!$consume) {
                $context->recordAttempt($code, true);
                $context->markAsVerified();

                $context->setFinalResult(new OtpResultRecord(
                    is_success: true,
                    message: $this->translator->trans($this->messageConfig->verifySuccess()),
                    data: new StrictDataObject([
                        'meta' => $otpModel->meta,
                        'consumed' => false,
                        'already_verified' => true,
                    ]),
                    occurred_at: new DateTimeVO(null),
                ));
                $context->setCurrentStep(OtpProcessingStep::VERIFIED);

                return $context;
            }

            if ($consume) {
                if (!$this->verifyCode($otpModel, $code)) {
                    return $this->handleFailedVerification($otpModel, $rateLimitKey, $context, $code);
                }

                $this->markAsUsed($otpModel);
                $context->recordAttempt($code, true);
                $context->markAsVerified();
                $context->markAsConsumed();

                $this->rateLimiter->clear($rateLimitKey);
                $this->rateLimiter->clear($this->buildRequestRateLimitKey($otpable, $context));

                $context->setFinalResult(new OtpResultRecord(
                    is_success: true,
                    message: $this->translator->trans($this->messageConfig->verifySuccess()),
                    data: new StrictDataObject([
                        'meta' => $otpModel->meta,
                        'consumed' => true,
                    ]),
                    occurred_at: new DateTimeVO(null),
                ));
                $context->setCurrentStep(OtpProcessingStep::VERIFIED);

                return $context;
            }
        }

        if (!$this->verifyCode($otpModel, $code)) {
            return $this->handleFailedVerification($otpModel, $rateLimitKey, $context, $code);
        }

        return $this->handleSuccessfulVerification($otpModel, $rateLimitKey, $context, $code, $consume, $otpable);
    }

    public function cancel(Model $otpable, OtpProcessingContext $context): OtpProcessingContext
    {
        $cancelledCount = $this->cancelPendingOtps($otpable, $context);

        $message = $cancelledCount > 0
            ? $this->translator->trans($this->messageConfig->cancelSuccess(), ['count' => $cancelledCount])
            : $this->translator->trans($this->messageConfig->noPendingToCancel());

        $context->setFinalResult(new OtpResultRecord(
            is_success: true,
            message: $message,
            data: new StrictDataObject(['cancelled_count' => $cancelledCount]),
            occurred_at: new DateTimeVO(null),
        ));
        $context->setCurrentStep(OtpProcessingStep::CANCELLED);

        return $context;
    }

    // ============================================================================
    // Private Helper Methods
    // ============================================================================

    private function verifyCode(OneTimePassword $model, string $plainCode): bool
    {
        return $this->hash->check($plainCode, $model->token_hash);
    }

    private function markAsVerified(OneTimePassword $model): void
    {
        $model->verified_at = now();
        $model->save();
    }

    private function markAsUsed(OneTimePassword $model): void
    {
        $model->used_at = now();
        $model->save();
    }

    private function cancelOtpModel(OneTimePassword $model): void
    {
        $model->cancelled_at = now();
        $model->save();
    }

    private function incrementAttempts(OneTimePassword $model): void
    {
        $model->attempts++;
        $model->save();
    }

    private function hashPlainCode(string $plainCode): string
    {
        return $this->hash->make($plainCode);
    }

    private function isRateLimitExceeded(string $key, int $limit): bool
    {
        return $this->rateLimiter->isExceeded($key, $limit);
    }

    private function recordRateLimitHit(string $key): void
    {
        $this->rateLimiter->hit($key, $this->config->otpSecurityConfig()->rate_limit_hit_decay_seconds);
    }

    private function recordFailedVerificationAttempt(string $key): void
    {
        $this->rateLimiter->hit($key, $this->config->otpSecurityConfig()->failed_verification_decay_seconds);
    }

    private function createOtpModel(
        Model $otpable,
        OtpProcessingContext $context,
        ?array $channels,
        ?array $metadata,
        int $expiresInMinutes,
        int $maxAttempts,
        string $plainCode,
    ): OneTimePassword {
        $data = [
            'otpable_type' => $otpable->getMorphClass(),
            'otpable_id' => $otpable->getKey(),
            'token_hash' => $this->hashPlainCode($plainCode),
            'type' => $context->getType(),
            'destination' => $context->getDestination(),
            'channels' => $channels,
            'meta' => $metadata,
            'max_attempts' => $maxAttempts,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ];

        return $this->otpRepository->createRaw($data);
    }

    private function sendOtpNotification(Model $otpable, OneTimePassword $otpModel, string $plainCode): bool
    {
        try {
            $notification = new OtpNotification($otpModel, $plainCode, $this->translator);
            $otpable->notify($notification);
            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to send OTP notification', [
                'otpable_type' => $otpable->getMorphClass(),
                'otpable_id' => $otpable->getKey(),
                'type' => $otpModel->type,
                'destination' => $otpModel->destination,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    private function findOtpForVerification(Model $otpable, OtpProcessingContext $context): ?OneTimePassword
    {
        return $this->otpRepository->findValidOtpForVerification(
            otpable: $otpable,
            type: $context->getType(),
            destination: $context->getDestination(),
        );
    }

    private function findPendingOtp(Model $otpable, OtpProcessingContext $context): ?OneTimePassword
    {
        return $this->otpRepository->findPendingOtp(
            otpable: $otpable,
            type: $context->getType(),
            destination: $context->getDestination(),
        );
    }

    private function deleteOldPendingOtps(Model $otpable, OtpProcessingContext $context): int
    {
        return $this->otpRepository->deleteOldPendingOtps(
            otpable: $otpable,
            type: $context->getType(),
            destination: $context->getDestination(),
        );
    }

    private function cancelPendingOtps(Model $otpable, OtpProcessingContext $context): int
    {
        return $this->otpRepository->cancelPendingOtps(
            otpable: $otpable,
            type: $context->getType(),
            destination: $context->getDestination(),
        );
    }

    private function handleFailedVerification(
        OneTimePassword $otpModel,
        string $rateLimitKey,
        OtpProcessingContext $context,
        string $code,
    ): OtpProcessingContext {
        $this->incrementAttempts($otpModel);
        $this->recordFailedVerificationAttempt($rateLimitKey);

        $maxAttempts = $otpModel->max_attempts ?? $this->config->otpConfig()->default_max_attempts;
        $remainingAttempts = $maxAttempts - $otpModel->attempts;

        $context->recordAttempt($code, false, $this->translator->trans($this->messageConfig->invalidCodeAttemptsRemaining(), ['attempts' => $remainingAttempts]));

        if ($remainingAttempts <= 0) {
            $this->cancelOtpModel($otpModel);
            $context->setError($this->translator->trans($this->messageConfig->maxAttemptsExceeded()));
            $context->setFinalResult(new OtpResultRecord(
                is_success: false,
                message: $this->translator->trans($this->messageConfig->maxAttemptsExceeded()),
                data: null,
                occurred_at: new DateTimeVO(null),
            ));
            return $context;
        }

        $message = $remainingAttempts > 1
            ? $this->translator->trans($this->messageConfig->invalidCodeAttemptsRemaining(), ['attempts' => $remainingAttempts])
            : $this->translator->trans($this->messageConfig->invalidCodeOneAttemptRemaining());

        $context->setError($message);
        $context->setFinalResult(new OtpResultRecord(
            is_success: false,
            message: $message,
            data: new StrictDataObject(['remaining_attempts' => $remainingAttempts]),
            occurred_at: new DateTimeVO(null),
        ));
        return $context;
    }

    private function handleSuccessfulVerification(
        OneTimePassword $otpModel,
        string $rateLimitKey,
        OtpProcessingContext $context,
        string $code,
        bool $consume,
        Model $otpable,
    ): OtpProcessingContext {
        if (!$otpModel->isVerified()) {
            $this->markAsVerified($otpModel);
        }

        $context->recordAttempt($code, true);
        $context->markAsVerified();

        if ($consume) {
            $this->markAsUsed($otpModel);
            $context->markAsConsumed();
        }

        $this->rateLimiter->clear($rateLimitKey);
        $this->rateLimiter->clear($this->buildRequestRateLimitKey($otpable, $context));

        $context->setFinalResult(new OtpResultRecord(
            is_success: true,
            message: $this->translator->trans($this->messageConfig->verifySuccess()),
            data: new StrictDataObject([
                'meta' => $otpModel->meta,
                'consumed' => $consume,
            ]),
            occurred_at: new DateTimeVO(null),
        ));
        $context->setCurrentStep(OtpProcessingStep::VERIFIED);

        return $context;
    }

    private function buildRequestRateLimitKey(Model $otpable, OtpProcessingContext $context): string
    {
        return sprintf(
            'otp_request:%s:%d:%s:%s',
            $otpable->getMorphClass(),
            $otpable->getKey(),
            $context->getType(),
            md5($context->getDestination())
        );
    }

    private function buildVerificationRateLimitKey(Model $otpable, OtpProcessingContext $context): string
    {
        return sprintf(
            'otp_verify:%s:%d:%s:%s',
            $otpable->getMorphClass(),
            $otpable->getKey(),
            $context->getType(),
            md5($context->getDestination())
        );
    }
}
