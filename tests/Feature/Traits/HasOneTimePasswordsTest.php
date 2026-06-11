<?php

// tests/Feature/Traits/HasOneTimePasswordsTest.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Feature\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;
use AndyDefer\Mfa\Otp\Data\OtpResponseData;
use AndyDefer\Mfa\Otp\Models\OneTimePassword;
use AndyDefer\Mfa\Otp\Services\OtpService;
use AndyDefer\Mfa\Tests\Support\TestUser;
use AndyDefer\Mfa\Tests\TestCase;
use Mockery;

/**
 * Test suite for the HasOneTimePasswords trait.
 *
 * Validates that models using the trait correctly interact with the OtpService
 * and provide the expected OTP management functionality.
 */
final class HasOneTimePasswordsTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $testUser;

    private string $testType;

    private string $testDestination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $this->testType = 'email_verification';
        $this->testDestination = 'john@example.com';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_one_time_passwords_returns_morph_many_relation(): void
    {
        // Arrange: Create some OTPs for the user
        $otp1 = OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => 'hash1',
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->addMinutes(10),
        ]);

        $otp2 = OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => 'hash2',
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->addMinutes(10),
        ]);

        // Act: Get the oneTimePasswords relation
        $otps = $this->testUser->oneTimePasswords;

        // Assert: Verify relation returns the correct OTPs
        $this->assertCount(2, $otps);
        $this->assertTrue($otps->contains($otp1));
        $this->assertTrue($otps->contains($otp2));
    }

    public function test_send_otp_calls_service_send_method(): void
    {
        // Arrange: Mock the OtpService
        $otpServiceMock = Mockery::mock(OtpService::class);
        $otpServiceMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::on(function ($argument) {
                    return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                }),
                $this->testType,
                $this->testDestination,
                null,
                null,
                null,
                null
            )
            ->andReturn(OtpResponseData::success());

        $this->app->instance(OtpService::class, $otpServiceMock);

        // Act: Call sendOtp on the user model
        $response = $this->testUser->sendOtp(
            $this->testType,
            $this->testDestination
        );

        // Assert: Verify the response is successful
        $this->assertTrue($response->isSuccess());
    }

    public function test_send_otp_passes_custom_parameters_to_service(): void
    {
        // Arrange: Mock the OtpService
        $channels = ['sms'];
        $meta = ['ip' => '127.0.0.1'];
        $expiresInMinutes = 5;
        $maxAttempts = 2;

        $otpServiceMock = Mockery::mock(OtpService::class);
        $otpServiceMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::on(function ($argument) {
                    return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                }),
                $this->testType,
                $this->testDestination,
                $channels,
                $meta,
                $expiresInMinutes,
                $maxAttempts
            )
            ->andReturn(OtpResponseData::success());

        $this->app->instance(OtpService::class, $otpServiceMock);

        // Act: Call sendOtp with custom parameters
        $response = $this->testUser->sendOtp(
            type: $this->testType,
            destination: $this->testDestination,
            channels: $channels,
            meta: $meta,
            expiresInMinutes: $expiresInMinutes,
            maxAttempts: $maxAttempts
        );

        // Assert: Verify the response is successful
        $this->assertTrue($response->isSuccess());
    }

    public function test_resend_otp_calls_service_resend_method(): void
    {
        // Arrange: Mock the OtpService
        $otpServiceMock = Mockery::mock(OtpService::class);
        $otpServiceMock->shouldReceive('resend')
            ->once()
            ->with(
                Mockery::on(function ($argument) {
                    return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                }),
                $this->testType,
                $this->testDestination,
                null,
                null,
                null,
                null
            )
            ->andReturn(OtpResponseData::success());

        $this->app->instance(OtpService::class, $otpServiceMock);

        // Act: Call resendOtp on the user model
        $response = $this->testUser->resendOtp(
            $this->testType,
            $this->testDestination
        );

        // Assert: Verify the response is successful
        $this->assertTrue($response->isSuccess());
    }

    public function test_verify_otp_calls_service_verify_method(): void
    {
        // Arrange: Mock the OtpService
        $code = '123456';
        $consume = true;

        $otpServiceMock = Mockery::mock(OtpService::class);
        $otpServiceMock->shouldReceive('verify')
            ->once()
            ->with(
                Mockery::on(function ($argument) {
                    return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                }),
                $code,
                $this->testType,
                $this->testDestination,
                $consume
            )
            ->andReturn(OtpResponseData::success());

        $this->app->instance(OtpService::class, $otpServiceMock);

        // Act: Call verifyOtp on the user model
        $response = $this->testUser->verifyOtp(
            code: $code,
            type: $this->testType,
            destination: $this->testDestination,
            consume: $consume
        );

        // Assert: Verify the response is successful
        $this->assertTrue($response->isSuccess());
    }

    public function test_cancel_otps_cancels_pending_otps(): void
    {
        // Arrange: Create a pending OTP
        $otp = OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', '123456'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertNull($otp->cancelled_at);

        // Act: Cancel OTPs
        $cancelledCount = $this->testUser->cancelOtps($this->testType, $this->testDestination);

        // Assert: Verify the OTP was cancelled
        $this->assertEquals(1, $cancelledCount);

        $otp->refresh();
        $this->assertNotNull($otp->cancelled_at);
    }

    public function test_cancel_otps_returns_zero_when_no_pending_otps(): void
    {
        // Act: Cancel OTPs without any existing OTP
        $cancelledCount = $this->testUser->cancelOtps($this->testType, $this->testDestination);

        // Assert: Verify count is zero
        $this->assertEquals(0, $cancelledCount);
    }

    public function test_get_pending_otp_returns_valid_otp(): void
    {
        // Arrange: Create a valid OTP
        $otp = OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', '123456'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Act: Get pending OTP
        $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

        // Assert: Verify the correct OTP was found
        $this->assertNotNull($foundOtp);
        $this->assertEquals($otp->id, $foundOtp->id);
    }

    public function test_get_pending_otp_returns_null_for_expired_otp(): void
    {
        // Arrange: Create an expired OTP
        OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', '123456'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->subMinutes(10),
        ]);

        // Act: Get pending OTP
        $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

        // Assert: Verify no OTP was found
        $this->assertNull($foundOtp);
    }

    public function test_get_pending_otp_returns_null_for_used_otp(): void
    {
        // Arrange: Create a used OTP
        OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', '123456'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->addMinutes(10),
            'used_at' => now(),
        ]);

        // Act: Get pending OTP
        $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

        // Assert: Verify no OTP was found
        $this->assertNull($foundOtp);
    }

    public function test_get_pending_otp_returns_null_for_verified_otp(): void
    {
        // Arrange: Create a verified OTP
        OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', '123456'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
        ]);

        // Act: Get pending OTP
        $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

        // Assert: Verify no OTP was found
        $this->assertNull($foundOtp);
    }

    public function test_get_pending_otp_returns_null_for_cancelled_otp(): void
    {
        // Arrange: Create a cancelled OTP
        OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', '123456'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->addMinutes(10),
            'cancelled_at' => now(),
        ]);

        // Act: Get pending OTP
        $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

        // Assert: Verify no OTP was found
        $this->assertNull($foundOtp);
    }

    public function test_has_valid_otp_returns_true_when_valid_otp_exists(): void
    {
        // Arrange: Create a valid OTP
        OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', '123456'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Act: Check if valid OTP exists
        $hasValid = $this->testUser->hasValidOtp($this->testType, $this->testDestination);

        // Assert: Verify true is returned
        $this->assertTrue($hasValid);
    }

    public function test_has_valid_otp_returns_false_when_no_valid_otp_exists(): void
    {
        // Act: Check if valid OTP exists without any OTP
        $hasValid = $this->testUser->hasValidOtp($this->testType, $this->testDestination);

        // Assert: Verify false is returned
        $this->assertFalse($hasValid);
    }

    public function test_cleanup_expired_otps_deletes_expired_otps(): void
    {
        // Arrange: Create expired and valid OTPs
        $expiredOtp = OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', 'expired'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->subMinutes(10),
        ]);

        $validOtp = OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', 'valid'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Act: Clean up expired OTPs
        $deletedCount = $this->testUser->cleanupExpiredOtps();

        // Assert: Verify only expired OTP was deleted
        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseMissing('one_time_passwords', ['id' => $expiredOtp->id]);
        $this->assertDatabaseHas('one_time_passwords', ['id' => $validOtp->id]);
    }

    public function test_cleanup_expired_otps_does_not_delete_verified_or_used_otps(): void
    {
        // Arrange: Create expired but verified OTP
        $expiredVerifiedOtp = OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => hash('sha256', 'expired_verified'),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'expires_at' => now()->subMinutes(10),
            'verified_at' => now()->subMinutes(20),
        ]);

        // Act: Clean up expired OTPs
        $deletedCount = $this->testUser->cleanupExpiredOtps();

        // Assert: Verified OTP should not be deleted
        $this->assertEquals(0, $deletedCount);
        $this->assertDatabaseHas('one_time_passwords', ['id' => $expiredVerifiedOtp->id]);
    }
}
