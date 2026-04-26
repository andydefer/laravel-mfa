<?php

declare(strict_types=1);

namespace Kani\Mfa\Tests\Feature\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Kani\Mfa\Core\Helpers\TranslationHelper;
use Kani\Mfa\Otp\Models\OneTimePassword;
use Kani\Mfa\Otp\Notifications\OtpNotification;
use Kani\Mfa\Otp\Services\DefaultCodeGenerator;
use Kani\Mfa\Otp\Services\LaravelRateLimiter;
use Kani\Mfa\Otp\Services\OtpService;
use Kani\Mfa\Tests\Support\TestUser;
use Kani\Mfa\Tests\TestCase;

/**
 * Test suite for OtpService core functionality.
 *
 * Validates OTP lifecycle operations including sending, resending,
 * verification, cancellation, rate limiting, and edge cases.
 */
final class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otpService;

    private TestUser $testUser;

    private string $testType;

    private string $testDestination;

    /**
     * Setup test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set English locale for tests
        config()->set('otp.localization.locale', 'en');
        config()->set('otp.localization.fallback_locale', 'en');

        // Arrange: Create service dependencies
        $codeGenerator = new DefaultCodeGenerator;
        $rateLimiter = new LaravelRateLimiter;

        // Act: Instantiate OTP service with test configuration including decay settings
        $this->otpService = new OtpService(
            codeGenerator: $codeGenerator,
            rateLimiter: $rateLimiter,
            defaultExpiryMinutes: 10,
            defaultMaxAttempts: 3,
            rateLimitRequests: 3,
            rateLimitVerifications: 5,
            rateLimitDecayMinutes: 60,
            failedVerificationDecaySeconds: 300,
            rateLimitHitDecaySeconds: 60
        );

        // Arrange: Create test user and set test parameters
        $this->testUser = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $this->testType = 'email_verification';
        $this->testDestination = 'john@example.com';

        // Arrange: Fake notifications to prevent actual email sending
        Notification::fake();
    }

    /**
     * Test that send creates an OTP record and returns success response.
     */
    public function test_send_creates_otp_and_returns_success(): void
    {
        // Act: Send a new OTP
        $response = $this->otpService->send(
            $this->testUser,
            $this->testType,
            $this->testDestination
        );

        // Assert: Response indicates success
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(TranslationHelper::trans('messages.send_success'), $response->message);

        // Assert: OTP record exists in database
        $otp = OneTimePassword::where('otpable_id', $this->testUser->id)->first();
        $this->assertNotNull($otp);
        $this->assertEquals($this->testType, $otp->type);
        $this->assertEquals($this->testDestination, $otp->destination);

        // Assert: Notification was queued/sent
        Notification::assertSentTo($this->testUser, OtpNotification::class);
    }

    /**
     * Test that send respects custom expiry time and max attempts values.
     */
    public function test_send_respects_custom_expiry_and_max_attempts(): void
    {
        // Arrange: Set custom parameters
        $customExpiry = 5;
        $customMaxAttempts = 2;

        // Act: Send OTP with custom values
        $response = $this->otpService->send(
            $this->testUser,
            $this->testType,
            $this->testDestination,
            expiresInMinutes: $customExpiry,
            maxAttempts: $customMaxAttempts
        );

        // Assert: Response contains custom expiry value
        $this->assertTrue($response->isSuccess());
        $this->assertEquals($customExpiry, $response->data['expires_in_minutes']);

        // Assert: OTP record has custom max attempts
        $otp = OneTimePassword::where('otpable_id', $this->testUser->id)->first();
        $this->assertEquals($customMaxAttempts, $otp->max_attempts);
    }

    /**
     * Test that send stores channels and metadata when provided.
     */
    public function test_send_stores_channels_and_metadata(): void
    {
        // Arrange: Prepare custom channels and metadata
        $channels = ['mail', 'sms'];
        $metadata = ['ip' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0'];

        // Act: Send OTP with channels and metadata
        $response = $this->otpService->send(
            $this->testUser,
            $this->testType,
            $this->testDestination,
            channels: $channels,
            metadata: $metadata
        );

        // Assert: OTP stored channels and metadata correctly
        $this->assertTrue($response->isSuccess());

        $otp = OneTimePassword::where('otpable_id', $this->testUser->id)->first();
        $this->assertEquals($channels, $otp->channels);
        $this->assertEquals($metadata, $otp->meta);
    }

    /**
     * Test that send returns rate limited response when request limit exceeded.
     */
    public function test_send_returns_rate_limited_response_when_exceeded(): void
    {
        // Arrange: Generate rate limit key for the user
        $rateLimitKey = 'otp_request:'.$this->testUser->getMorphClass().':'.$this->testUser->id.':'.$this->testType.':'.md5($this->testDestination);

        // Arrange: Exceed rate limit by hitting the key 4 times (limit is 3)
        RateLimiter::hit($rateLimitKey, 60);
        RateLimiter::hit($rateLimitKey, 60);
        RateLimiter::hit($rateLimitKey, 60);
        RateLimiter::hit($rateLimitKey, 60);

        // Act: Attempt to send OTP when rate limited
        $response = $this->otpService->send(
            $this->testUser,
            $this->testType,
            $this->testDestination
        );

        // Assert: Rate limited response returned
        $this->assertFalse($response->isSuccess());
        $this->assertEquals('rate_limited', $response->status->value);
    }

    /**
     * Test that resend creates a new OTP when no pending OTP exists.
     */
    public function test_resend_creates_new_otp_when_no_pending(): void
    {
        // Act: Resend OTP without prior send
        $response = $this->otpService->resend(
            $this->testUser,
            $this->testType,
            $this->testDestination
        );

        // Assert: Response is successful
        $this->assertTrue($response->isSuccess());

        // Assert: Only one OTP was created
        $otps = OneTimePassword::where('otpable_id', $this->testUser->id)->get();
        $this->assertCount(1, $otps);
    }

    /**
     * Test that resend cancels existing pending OTP and creates a new one.
     */
    public function test_resend_cancels_old_otp_and_creates_new(): void
    {
        // Arrange: Send initial OTP
        $this->otpService->send($this->testUser, $this->testType, $this->testDestination);
        $firstOtp = OneTimePassword::where('otpable_id', $this->testUser->id)->first();

        // Act: Resend OTP
        $response = $this->otpService->resend($this->testUser, $this->testType, $this->testDestination);

        // Assert: Response is successful with correct message
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(TranslationHelper::trans('messages.resend_success'), $response->message);

        // Assert: First OTP was cancelled
        $firstOtp->refresh();
        $this->assertNotNull($firstOtp->cancelled_at);

        // Assert: Two OTPs exist (one cancelled, one new)
        $otps = OneTimePassword::where('otpable_id', $this->testUser->id)->get();
        $this->assertCount(2, $otps);
    }

    /**
     * Test that resend reuses previous channels and metadata when not provided.
     */
    public function test_resend_uses_previous_channels_and_metadata_when_not_provided(): void
    {
        // Arrange: Send initial OTP with custom channels and metadata
        $channels = ['sms'];
        $metadata = ['test' => 'value'];

        $this->otpService->send(
            $this->testUser,
            $this->testType,
            $this->testDestination,
            channels: $channels,
            metadata: $metadata
        );

        // Act: Resend OTP without providing channels/metadata
        $this->otpService->resend($this->testUser, $this->testType, $this->testDestination);

        // Assert: New OTP inherited channels and metadata
        $newOtp = OneTimePassword::where('otpable_id', $this->testUser->id)
            ->whereNull('cancelled_at')
            ->first();

        $this->assertEquals($channels, $newOtp->channels);
        $this->assertEquals($metadata, $newOtp->meta);
    }

    /**
     * Test that verify returns invalid code response for wrong verification code.
     */
    public function test_verify_returns_invalid_code_for_wrong_code(): void
    {
        // Arrange: Send OTP first
        $this->otpService->send($this->testUser, $this->testType, $this->testDestination);

        // Act: Attempt verification with wrong code
        $response = $this->otpService->verify(
            $this->testUser,
            '000000',
            $this->testType,
            $this->testDestination
        );

        // Assert: Invalid code response returned
        $this->assertFalse($response->isSuccess());
        $this->assertEquals('invalid_code', $response->status->value);
    }

    /**
     * Test that verify increments attempts counter on each failed verification.
     */
    public function test_verify_increments_attempts_on_failure(): void
    {
        // Arrange: Send OTP and verify initial attempts count
        $this->otpService->send($this->testUser, $this->testType, $this->testDestination);
        $otp = OneTimePassword::where('otpable_id', $this->testUser->id)->first();
        $this->assertEquals(0, $otp->attempts);

        // Act: Perform failed verification
        $this->otpService->verify($this->testUser, '000000', $this->testType, $this->testDestination);

        // Assert: Attempts counter incremented
        $otp->refresh();
        $this->assertEquals(1, $otp->attempts);
    }

    /**
     * Test that verify returns max attempts exceeded after reaching attempt limit.
     */
    public function test_verify_returns_max_attempts_exceeded_after_too_many_failures(): void
    {
        // Arrange: Send OTP with max attempts set to 3
        $this->otpService->send($this->testUser, $this->testType, $this->testDestination, maxAttempts: 3);
        $otp = OneTimePassword::where('otpable_id', $this->testUser->id)->first();

        // Act: Perform 3 failed verification attempts
        for ($attemptNumber = 0; $attemptNumber < 3; $attemptNumber++) {
            $this->otpService->verify($this->testUser, '000000', $this->testType, $this->testDestination);
        }

        // Assert: OTP cancelled after max attempts
        $otp->refresh();
        $this->assertEquals(3, $otp->attempts);
        $this->assertNotNull($otp->cancelled_at);
    }

    /**
     * Test that verify returns expired code response for expired OTP.
     */
    public function test_verify_returns_expired_code_for_expired_otp(): void
    {
        // Arrange: Create an expired OTP directly in database (bypassing send which deletes old pending OTPs)
        $plainCode = '123456';
        $otp = OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => Hash::make($plainCode),
            'type' => $this->testType,
            'destination' => $this->testDestination,
            'channels' => null,
            'meta' => null,
            'attempts' => 0,
            'max_attempts' => 3,
            'expires_at' => now()->subMinutes(1), // Already expired
        ]);

        // Assert: OTP is expired
        $this->assertTrue($otp->isExpired());

        // Act: Attempt verification on expired OTP
        $response = $this->otpService->verify(
            $this->testUser,
            $plainCode,
            $this->testType,
            $this->testDestination
        );

        // Assert: Expired code response returned
        $this->assertFalse($response->isSuccess());
        $this->assertEquals('expired_code', $response->status->value);
        $this->assertEquals(
            TranslationHelper::trans('messages.expired_code'),
            $response->message
        );
    }

    /**
     * Test that cancel cancels all pending OTPs for the user.
     */
    public function test_cancel_cancels_pending_otps(): void
    {
        // Arrange: Send OTP and verify it's not cancelled
        $this->otpService->send($this->testUser, $this->testType, $this->testDestination);
        $otp = OneTimePassword::where('otpable_id', $this->testUser->id)->first();
        $this->assertNull($otp->cancelled_at);

        // Act: Cancel pending OTPs
        $response = $this->otpService->cancel($this->testUser, $this->testType, $this->testDestination);

        // Assert: OTP was cancelled
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(TranslationHelper::trans('messages.cancel_success', ['count' => 1]), $response->message);

        $otp->refresh();
        $this->assertNotNull($otp->cancelled_at);
    }

    /**
     * Test that cancel returns appropriate message when no pending OTPs exist.
     */
    public function test_cancel_returns_no_pending_message_when_none_found(): void
    {
        // Act: Cancel OTPs when none exist
        $response = $this->otpService->cancel($this->testUser, $this->testType, $this->testDestination);

        // Assert: Success but with zero cancellations
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(TranslationHelper::trans('messages.no_pending_to_cancel'), $response->message);
        $this->assertEquals(0, $response->data['cancelled_count']);
    }

    /**
     * Test that send deletes old pending OTPs before creating a new one.
     */
    public function test_send_deletes_old_pending_otps(): void
    {
        // Arrange: Send first OTP
        $this->otpService->send($this->testUser, $this->testType, $this->testDestination);
        $firstOtpId = OneTimePassword::where('otpable_id', $this->testUser->id)->first()->id;

        // Act: Send second OTP
        $this->otpService->send($this->testUser, $this->testType, $this->testDestination);

        // Assert: First OTP no longer exists (deleted, not cancelled)
        $firstOtpExists = OneTimePassword::where('id', $firstOtpId)->exists();
        $this->assertFalse($firstOtpExists);
    }

    /**
     * Test that verify returns not found when no OTP exists for the user.
     */
    public function test_verify_returns_not_found_for_non_existent_otp(): void
    {
        // Act: Attempt verification without any OTP sent
        $response = $this->otpService->verify($this->testUser, '123456', $this->testType, $this->testDestination);

        // Assert: Not found response returned
        $this->assertFalse($response->isSuccess());
        $this->assertEquals('not_found', $response->status->value);
    }
}
