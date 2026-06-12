<?php

// tests/Integration/Otp/Services/OtpServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Integration\Otp\Services;

use AndyDefer\Mfa\Configs\MfaConfig;
use AndyDefer\Mfa\Core\Configs\MessageConfig;
use AndyDefer\Mfa\Core\Contracts\Configs\MessageConfigInterface;
use AndyDefer\Mfa\Core\Services\TranslationService;
use AndyDefer\Mfa\Otp\Contexts\OtpProcessingContext;
use AndyDefer\Mfa\Otp\Enums\OtpProcessingStep;
use AndyDefer\Mfa\Otp\Models\OneTimePassword;
use AndyDefer\Mfa\Otp\Records\OneTimePasswordFilterRecord;
use AndyDefer\Mfa\Otp\Repositories\OneTimePasswordRepository;
use AndyDefer\Mfa\Otp\Services\DefaultCodeGenerator;
use AndyDefer\Mfa\Otp\Services\LaravelRateLimiter;
use AndyDefer\Mfa\Otp\Services\OtpService;
use AndyDefer\Mfa\Tests\Fixtures\Models\TestUser;
use AndyDefer\Mfa\Tests\IntegrationTestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\Cache;

final class OtpServiceTest extends IntegrationTestCase
{
    private OtpService $service;
    private TestUser $user;
    private OneTimePasswordRepository $repository;
    private MfaConfig $mfaConfig;
    private MessageConfigInterface $messageConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mfaConfig = new MfaConfig;
        $this->messageConfig = new MessageConfig;

        $codeGenerator = new DefaultCodeGenerator;
        $rateLimiter = new LaravelRateLimiter(Cache::store('array'));
        $translator = new TranslationService(app('translator'), $this->mfaConfig);

        $hashManager = new class(app()) extends HashManager {
            public function getDefaultDriver()
            {
                return 'bcrypt';
            }
        };
        $hashManager->extend('bcrypt', function () {
            return new BcryptHasher();
        });

        $this->repository = new OneTimePasswordRepository;

        $this->service = new OtpService(
            codeGenerator: $codeGenerator,
            rateLimiter: $rateLimiter,
            translator: $translator,
            messageConfig: $this->messageConfig,
            hash: $hashManager,
            otpRepository: $this->repository,
            config: $this->mfaConfig,
        );

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        $filter = new OneTimePasswordFilterRecord(
            otpable_type: $this->user->getMorphClass(),
            otpable_id: $this->user->id,
        );
        $this->repository->deleteBulk($filter);
        parent::tearDown();
    }

    // ============================================================================
    // send() Integration Tests
    // ============================================================================

    public function test_send_creates_otp_in_database(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');

        // Act
        $result = $this->service->send($this->user, $context);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $this->assertTrue($result->getFinalResult()->is_success);
        $this->assertSame(OtpProcessingStep::SENT, $result->getCurrentStep());
        $this->assertNotNull($otp);
        $this->assertSame('email_verification', $otp->type);
        $this->assertSame('user@example.com', $otp->destination);
        $this->assertSame($this->user->getMorphClass(), $otp->otpable_type);
        $this->assertSame($this->user->id, $otp->otpable_id);
        $this->assertNull($otp->verified_at);
        $this->assertNull($otp->used_at);
        $this->assertNull($otp->cancelled_at);
    }

    public function test_send_stores_hashed_token_not_plain_code(): void
    {
        // Arrange
        $context = new OtpProcessingContext('login', 'user@example.com');

        // Act
        $result = $this->service->send($this->user, $context);
        $plainCode = $result->getPlainCode();
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $this->assertNotNull($otp);
        $this->assertNotEquals($plainCode, $otp->token_hash);
        $this->assertTrue(app('hash')->check($plainCode, $otp->token_hash));
    }

    public function test_send_assigns_correct_expiry_time(): void
    {
        // Arrange
        $context = new OtpProcessingContext('password_reset', 'user@example.com');

        // Act
        $this->service->send($this->user, $context);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $expectedExpiry = now()->addMinutes(10);
        $this->assertNotNull($otp);
        $this->assertEqualsWithDelta($expectedExpiry->timestamp, $otp->expires_at->timestamp, 1);
    }

    public function test_send_creates_only_one_active_otp(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');

        // Act
        $this->service->send($this->user, $context);
        $firstOtpId = $this->repository->findLatestOtp($this->user)->id;

        $this->service->send($this->user, $context);
        $secondOtpId = $this->repository->findLatestOtp($this->user)->id;

        $activeOtps = $this->repository->findActiveOtps($this->user);

        // Assert
        $this->assertCount(1, $activeOtps);
        $this->assertEquals($secondOtpId, $activeOtps->first()->id);
        $this->assertNotEquals($firstOtpId, $secondOtpId);
    }

    public function test_send_with_metadata_stores_metadata(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $metadata = ['ip' => '127.0.0.1', 'user_agent' => 'PHPUnit'];

        // Act
        $this->service->send($this->user, $context, null, $metadata);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $this->assertNotNull($otp);
        $this->assertEquals($metadata, $otp->meta);
    }

    public function test_send_returns_expiry_metadata_in_context(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');

        // Act
        $result = $this->service->send($this->user, $context);
        $data = $result->getFinalResult()->data;

        // Assert
        $this->assertNotNull($data);
        $this->assertArrayHasKey('expires_at', $data->toArray());
        $this->assertArrayHasKey('expires_in_minutes', $data->toArray());
    }

    public function test_send_returns_plain_code_in_context(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');

        // Act
        $result = $this->service->send($this->user, $context);

        // Assert
        $this->assertNotNull($result->getPlainCode());
        $this->assertIsString($result->getPlainCode());
        $this->assertEquals(6, strlen($result->getPlainCode()));
    }

    // ============================================================================
    // verify() Integration Tests
    // ============================================================================

    public function test_verify_successfully_validates_correct_code(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $sendResult = $this->service->send($this->user, $context);
        $plainCode = $sendResult->getPlainCode();

        // Act
        $verifyResult = $this->service->verify($this->user, $plainCode, $context);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $this->assertTrue($verifyResult->getFinalResult()->is_success);
        $this->assertSame(OtpProcessingStep::VERIFIED, $verifyResult->getCurrentStep());
        $this->assertTrue($verifyResult->isVerified());
        $this->assertNotNull($otp->verified_at);
    }

    public function test_verify_fails_with_wrong_code(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $this->service->send($this->user, $context);

        // Act
        $verifyResult = $this->service->verify($this->user, 'wrong_code', $context);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $this->assertFalse($verifyResult->getFinalResult()->is_success);
        $this->assertTrue($verifyResult->hasError());
        $this->assertNull($otp->verified_at);
    }

    public function test_verify_increments_attempts_on_failure(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $this->service->send($this->user, $context);

        // Act
        $this->service->verify($this->user, 'wrong1', $context);
        $this->service->verify($this->user, 'wrong2', $context);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $this->assertEquals(2, $otp->attempts);
    }

    public function test_verify_blocks_after_max_attempts(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $this->service->send($this->user, $context);

        // Act
        $this->service->verify($this->user, 'wrong1', $context);
        $this->service->verify($this->user, 'wrong2', $context);
        $this->service->verify($this->user, 'wrong3', $context);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $this->assertNotNull($otp->cancelled_at);
    }

    public function test_verify_fails_with_expired_otp(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $sendResult = $this->service->send($this->user, $context);
        $plainCode = $sendResult->getPlainCode();
        $otp = $this->repository->findLatestOtp($this->user);
        $otp->expires_at = now()->subMinute();
        $otp->save();

        // Act
        $verifyResult = $this->service->verify($this->user, $plainCode, $context);
        $otp->refresh();

        // Assert
        $this->assertFalse($verifyResult->getFinalResult()->is_success);
        $this->assertStringContainsString('expired', $verifyResult->getFinalResult()->message);
        $this->assertNotNull($otp->cancelled_at);
    }

    public function test_verify_cannot_use_same_otp_twice(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $sendResult = $this->service->send($this->user, $context);
        $plainCode = $sendResult->getPlainCode();

        // Act - Première vérification
        $firstVerify = $this->service->verify($this->user, $plainCode, $context, true);

        // Assert - Première vérification doit réussir
        $this->assertTrue($firstVerify->getFinalResult()->is_success);

        // Act - Deuxième vérification avec le même code
        $secondVerify = $this->service->verify($this->user, $plainCode, $context, true);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert - Deuxième vérification doit échouer et l'OTP être marqué comme utilisé
        $this->assertFalse($secondVerify->getFinalResult()->is_success);
        $this->assertNotNull($otp->used_at);
    }

    public function test_verify_without_consume_leaves_otp_valid(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $sendResult = $this->service->send($this->user, $context);
        $plainCode = $sendResult->getPlainCode();

        // Act - Première vérification SANS consommer
        $verifyResult = $this->service->verify($this->user, $plainCode, $context, false);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert - Vérification sans consommation
        $this->assertTrue($verifyResult->getFinalResult()->is_success);
        $this->assertTrue($verifyResult->isVerified());
        $this->assertFalse($verifyResult->isConsumed());
        $this->assertNotNull($otp->verified_at);
        $this->assertNull($otp->used_at);

        // Act - Deuxième vérification AVEC consommation
        $secondVerify = $this->service->verify($this->user, $plainCode, $context, true);
        $otp->refresh();

        // Assert - Vérification avec consommation
        $this->assertTrue($secondVerify->getFinalResult()->is_success);
        $this->assertNotNull($otp->used_at);
    }

    // ============================================================================
    // resend() Integration Tests
    // ============================================================================

    public function test_resend_cancels_old_otp_and_creates_new(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $firstSend = $this->service->send($this->user, $context);
        $firstOtpId = $this->repository->findLatestOtp($this->user)->id;
        $firstCode = $firstSend->getPlainCode();

        usleep(1000);
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::now()->addSecond());

        // Act
        $resendResult = $this->service->resend($this->user, $context);
        $newOtp = $this->repository->findLatestOtp($this->user);

        // Assert - Vérifier l'ancien OTP annulé
        $filter = new OneTimePasswordFilterRecord(id: $firstOtpId);
        $findByRecord = new FindByRecord(filters: $filter, limit: 1);
        $oldOtp = $this->repository->findBy($findByRecord)->first();

        $this->assertTrue($resendResult->getFinalResult()->is_success);
        $this->assertNotNull($oldOtp);
        $this->assertNotNull($oldOtp->cancelled_at);
        $this->assertNotNull($newOtp);
        $this->assertNotEquals($firstOtpId, $newOtp->id);
        $this->assertNotEquals($firstCode, $resendResult->getPlainCode());
    }

    public function test_resend_works_without_previous_otp(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');

        // Act
        $resendResult = $this->service->resend($this->user, $context);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $this->assertTrue($resendResult->getFinalResult()->is_success);
        $this->assertNotNull($otp);
    }

    public function test_resend_when_no_pending_otp_calls_send(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');

        // Act
        $result = $this->service->resend($this->user, $context);
        $otp = $this->repository->findLatestOtp($this->user);

        // Assert
        $this->assertTrue($result->getFinalResult()->is_success);
        $this->assertNotNull($otp);
    }

    // ============================================================================
    // cancel() Integration Tests
    // ============================================================================

    public function test_cancel_removes_pending_otps(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $this->service->send($this->user, $context);
        $otp = $this->repository->findLatestOtp($this->user);

        // Act
        $result = $this->service->cancel($this->user, $context);
        $otp->refresh();

        // Assert
        $this->assertTrue($result->getFinalResult()->is_success);
        $this->assertEquals(1, $result->getFinalResult()->data?->get('cancelled_count'));
        $this->assertNotNull($otp->cancelled_at);
    }

    // ============================================================================
    // Rate Limiting Integration Tests
    // ============================================================================

    public function test_rate_limiting_blocks_excessive_requests(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $uniqueUser = TestUser::create([
            'name' => 'Rate Limit Test ' . uniqid(),
            'email' => 'rate_' . uniqid() . '@example.com',
        ]);

        $freshRateLimiter = new LaravelRateLimiter(Cache::store('array'));
        $freshHashManager = new class(app()) extends HashManager {
            public function getDefaultDriver()
            {
                return 'bcrypt';
            }
        };
        $freshHashManager->extend('bcrypt', function () {
            return new BcryptHasher();
        });

        $tempService = new OtpService(
            codeGenerator: new DefaultCodeGenerator,
            rateLimiter: $freshRateLimiter,
            translator: new TranslationService(app('translator'), $this->mfaConfig),
            messageConfig: $this->messageConfig,
            hash: $freshHashManager,
            otpRepository: $this->repository,
            config: $this->mfaConfig,
        );

        // Act - 3 premières tentatives réussies
        for ($i = 0; $i < 3; $i++) {
            $result = $tempService->send($uniqueUser, $context);
            $this->assertTrue($result->getFinalResult()->is_success, "Attempt {$i} should succeed");
        }

        // Act - 4ème tentative bloquée
        $rateLimited = $tempService->send($uniqueUser, $context);

        // Assert
        $this->assertFalse($rateLimited->getFinalResult()->is_success);
        $this->assertStringContainsString('Please wait', $rateLimited->getFinalResult()->message);

        // Cleanup
        $filter = new OneTimePasswordFilterRecord(
            otpable_type: $uniqueUser->getMorphClass(),
            otpable_id: $uniqueUser->id,
        );
        $this->repository->deleteBulk($filter);
        $uniqueUser->delete();
    }

    // ============================================================================
    // Different OTP Types Tests
    // ============================================================================

    public function test_different_otp_types_are_independent(): void
    {
        // Arrange
        $context1 = new OtpProcessingContext('email_verification', 'user@example.com');
        $context2 = new OtpProcessingContext('login', 'user@example.com');

        // Act
        $this->service->send($this->user, $context1);
        $this->service->send($this->user, $context2);

        $filter = new OneTimePasswordFilterRecord(
            otpable_type: $this->user->getMorphClass(),
            otpable_id: $this->user->id,
        );
        $findByRecord = new FindByRecord(filters: $filter, sortBy: new SortColumns('id:asc'));
        $otps = $this->repository->findBy($findByRecord);
        $types = $otps->pluck('type')->toArray();

        // Assert
        $this->assertCount(2, $otps);
        $this->assertContains('email_verification', $types);
        $this->assertContains('login', $types);
    }

    public function test_different_destinations_are_independent(): void
    {
        // Arrange
        $context1 = new OtpProcessingContext('email_verification', 'user@example.com');
        $context2 = new OtpProcessingContext('email_verification', 'user+alt@example.com');

        // Act
        $this->service->send($this->user, $context1);
        $this->service->send($this->user, $context2);

        $filter = new OneTimePasswordFilterRecord(
            otpable_type: $this->user->getMorphClass(),
            otpable_id: $this->user->id,
        );
        $findByRecord = new FindByRecord(filters: $filter, sortBy: new SortColumns('id:asc'));
        $otps = $this->repository->findBy($findByRecord);
        $destinations = $otps->pluck('destination')->toArray();

        // Assert
        $this->assertCount(2, $otps);
        $this->assertContains('user@example.com', $destinations);
        $this->assertContains('user+alt@example.com', $destinations);
    }

    // ============================================================================
    // Repository Order Tests
    // ============================================================================

    public function test_repository_find_latest_otp_returns_correct_order(): void
    {
        // Arrange
        $context = new OtpProcessingContext('email_verification', 'user@example.com');
        $this->service->send($this->user, $context);
        sleep(1);
        $this->service->send($this->user, $context);

        // Act
        $latest = $this->repository->findLatestOtp($this->user);
        $all = $this->repository->findActiveOtps($this->user);

        // Assert
        $this->assertEquals($all->last()->id, $latest->id);
    }
}
