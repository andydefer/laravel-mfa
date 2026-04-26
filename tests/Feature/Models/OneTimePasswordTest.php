<?php
// tests/Feature/Models/OneTimePasswordTest.php

declare(strict_types=1);

namespace Kani\Mfa\Tests\Feature\Models;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Kani\Mfa\Otp\Models\OneTimePassword;
use Kani\Mfa\Tests\TestCase;

/**
 * Test suite for the OneTimePassword model.
 *
 * Validates all model functionality including state management,
 * verification logic, expiration handling, and polymorphic relationships.
 *
 * @package Kani\Mfa\Tests\Feature\Models
 */
final class OneTimePasswordTest extends TestCase
{
    use RefreshDatabase;

    private array $validAttributes;
    private string $plainCode;

    /**
     * Setup test environment.
     *
     * Creates valid attributes for OTP creation and stores the plain code
     * for verification tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Prepare test data
        $this->plainCode = '123456';

        $this->validAttributes = [
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => Hash::make($this->plainCode),
            'type' => 'email_verification',
            'destination' => 'user@example.com',
            'channels' => ['mail'],
            'meta' => ['ip' => '127.0.0.1'],
            'attempts' => 0,
            'max_attempts' => 3,
            'expires_at' => now()->addMinutes(10),
        ];
    }

    /**
     * Test that an OTP can be created with valid attributes.
     */
    public function test_otp_can_be_created(): void
    {
        // Act
        $otp = OneTimePassword::create($this->validAttributes);

        // Assert
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otp->id,
            'type' => 'email_verification',
            'destination' => 'user@example.com',
        ]);
        $this->assertInstanceOf(OneTimePassword::class, $otp);
    }

    /**
     * Test that fillable attributes are properly set.
     */
    public function test_fillable_attributes_are_set(): void
    {
        // Act
        $otp = OneTimePassword::create($this->validAttributes);

        // Assert
        $this->assertEquals('test', $otp->otpable_type);
        $this->assertEquals(1, $otp->otpable_id);
        $this->assertTrue(Hash::check($this->plainCode, $otp->token_hash));
        $this->assertEquals('email_verification', $otp->type);
        $this->assertEquals('user@example.com', $otp->destination);
        $this->assertEquals(['mail'], $otp->channels);
        $this->assertEquals(['ip' => '127.0.0.1'], $otp->meta);
        $this->assertEquals(0, $otp->attempts);
        $this->assertEquals(3, $otp->max_attempts);
    }

    /**
     * Test that casts work correctly.
     */
    public function test_casts_work_correctly(): void
    {
        // Act
        $otp = OneTimePassword::create($this->validAttributes);

        // Assert
        $this->assertIsArray($otp->channels);
        $this->assertIsArray($otp->meta);
        $this->assertIsInt($otp->attempts);
        $this->assertIsInt($otp->max_attempts);
        $this->assertInstanceOf(CarbonImmutable::class, $otp->expires_at);
    }

    /**
     * Test that isExpired returns true for expired OTP.
     */
    public function test_is_expired_returns_true_for_expired_otp(): void
    {
        // Arrange
        $attributes = array_merge($this->validAttributes, [
            'expires_at' => now()->subMinute(),
        ]);
        $otp = OneTimePassword::create($attributes);

        // Act & Assert
        $this->assertTrue($otp->isExpired());
    }

    /**
     * Test that isExpired returns false for non-expired OTP.
     */
    public function test_is_expired_returns_false_for_non_expired_otp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act & Assert
        $this->assertFalse($otp->isExpired());
    }

    /**
     * Test that isVerified returns true after marking as verified.
     */
    public function test_is_verified_returns_true_after_marking_as_verified(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act
        $otp->markAsVerified();

        // Assert
        $this->assertTrue($otp->isVerified());
        $this->assertNotNull($otp->verified_at);
    }

    /**
     * Test that isVerified returns false for unverified OTP.
     */
    public function test_is_verified_returns_false_for_unverified_otp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act & Assert
        $this->assertFalse($otp->isVerified());
        $this->assertNull($otp->verified_at);
    }

    /**
     * Test that isUsed returns true after marking as used.
     */
    public function test_is_used_returns_true_after_marking_as_used(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act
        $otp->markAsUsed();

        // Assert
        $this->assertTrue($otp->isUsed());
        $this->assertNotNull($otp->used_at);
    }

    /**
     * Test that isUsed returns false for unused OTP.
     */
    public function test_is_used_returns_false_for_unused_otp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act & Assert
        $this->assertFalse($otp->isUsed());
        $this->assertNull($otp->used_at);
    }

    /**
     * Test that isCancelled returns true after marking as cancelled.
     */
    public function test_is_cancelled_returns_true_after_marking_as_cancelled(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act
        $otp->markAsCancelled();

        // Assert
        $this->assertTrue($otp->isCancelled());
        $this->assertNotNull($otp->cancelled_at);
    }

    /**
     * Test that isCancelled returns false for active OTP.
     */
    public function test_is_cancelled_returns_false_for_active_otp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act & Assert
        $this->assertFalse($otp->isCancelled());
        $this->assertNull($otp->cancelled_at);
    }

    /**
     * Test that isValid returns true for valid OTP.
     */
    public function test_is_valid_returns_true_for_valid_otp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act & Assert
        $this->assertTrue($otp->isValid());
    }

    /**
     * Test that isValid returns false for expired OTP.
     */
    public function test_is_valid_returns_false_for_expired_otp(): void
    {
        // Arrange
        $attributes = array_merge($this->validAttributes, [
            'expires_at' => now()->subMinute(),
        ]);
        $otp = OneTimePassword::create($attributes);

        // Act & Assert
        $this->assertFalse($otp->isValid());
    }

    /**
     * Test that isValid returns false for verified OTP.
     */
    public function test_is_valid_returns_false_for_verified_otp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);
        $otp->markAsVerified();

        // Act & Assert
        $this->assertFalse($otp->isValid());
    }

    /**
     * Test that isValid returns false for used OTP.
     */
    public function test_is_valid_returns_false_for_used_otp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);
        $otp->markAsUsed();

        // Act & Assert
        $this->assertFalse($otp->isValid());
    }

    /**
     * Test that isValid returns false for cancelled OTP.
     */
    public function test_is_valid_returns_false_for_cancelled_otp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);
        $otp->markAsCancelled();

        // Act & Assert
        $this->assertFalse($otp->isValid());
    }

    /**
     * Test that hasExceededMaxAttempts returns true when attempts reach max.
     */
    public function test_has_exceeded_max_attempts_returns_true_when_attempts_reach_max(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act
        for ($i = 0; $i < 3; $i++) {
            $otp->incrementAttempts();
        }

        // Assert
        $this->assertTrue($otp->hasExceededMaxAttempts());
    }

    /**
     * Test that hasExceededMaxAttempts returns false when attempts below max.
     */
    public function test_has_exceeded_max_attempts_returns_false_when_attempts_below_max(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act
        $otp->incrementAttempts();

        // Assert
        $this->assertFalse($otp->hasExceededMaxAttempts());
    }

    /**
     * Test that verifyCode returns true for correct code.
     */
    public function test_verify_code_returns_true_for_correct_code(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act & Assert
        $this->assertTrue($otp->verifyCode($this->plainCode));
    }

    /**
     * Test that verifyCode returns false for incorrect code.
     */
    public function test_verify_code_returns_false_for_incorrect_code(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act & Assert
        $this->assertFalse($otp->verifyCode('999999'));
    }

    /**
     * Test that markAsVerified sets verified_at timestamp.
     */
    public function test_mark_as_verified_sets_verified_at_timestamp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);
        $before = now();

        // Act
        $result = $otp->markAsVerified();

        // Assert
        $this->assertSame($otp, $result);
        $this->assertTrue($otp->verified_at->greaterThanOrEqualTo($before));
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otp->id,
            'verified_at' => $otp->verified_at,
        ]);
    }

    /**
     * Test that markAsUsed sets used_at timestamp.
     */
    public function test_mark_as_used_sets_used_at_timestamp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);
        $before = now();

        // Act
        $result = $otp->markAsUsed();

        // Assert
        $this->assertSame($otp, $result);
        $this->assertTrue($otp->used_at->greaterThanOrEqualTo($before));
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otp->id,
            'used_at' => $otp->used_at,
        ]);
    }

    /**
     * Test that markAsCancelled sets cancelled_at timestamp.
     */
    public function test_mark_as_cancelled_sets_cancelled_at_timestamp(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);
        $before = now();

        // Act
        $result = $otp->markAsCancelled();

        // Assert
        $this->assertSame($otp, $result);
        $this->assertTrue($otp->cancelled_at->greaterThanOrEqualTo($before));
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otp->id,
            'cancelled_at' => $otp->cancelled_at,
        ]);
    }

    /**
     * Test that incrementAttempts increments the attempts counter.
     */
    public function test_increment_attempts_increments_attempts_counter(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);
        $initialAttempts = $otp->attempts;

        // Act
        $result = $otp->incrementAttempts();

        // Assert
        $this->assertSame($otp, $result);
        $this->assertEquals($initialAttempts + 1, $otp->attempts);
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otp->id,
            'attempts' => $otp->attempts,
        ]);
    }

    /**
     * Test that multiple incrementAttempts calls work correctly.
     */
    public function test_multiple_increment_attempts_calls_work_correctly(): void
    {
        // Arrange
        $otp = OneTimePassword::create($this->validAttributes);

        // Act
        $otp->incrementAttempts();
        $otp->incrementAttempts();
        $otp->incrementAttempts();

        // Assert
        $this->assertEquals(3, $otp->attempts);
    }

    /**
     * Test that different OTP types can be stored.
     */
    public function test_different_otp_types_can_be_stored(): void
    {
        // Arrange
        $types = ['email_verification', 'login', '2fa', 'payment_confirmation', 'delete_account'];

        // Act & Assert
        foreach ($types as $type) {
            $attributes = array_merge($this->validAttributes, ['type' => $type]);
            $otp = OneTimePassword::create($attributes);

            $this->assertEquals($type, $otp->type);
            $this->assertDatabaseHas('one_time_passwords', ['id' => $otp->id, 'type' => $type]);
        }
    }

    /**
     * Test that different channels can be stored as JSON.
     */
    public function test_different_channels_can_be_stored_as_json(): void
    {
        // Arrange
        $channelsList = [
            ['mail'],
            ['sms'],
            ['whatsapp'],
            ['mail', 'sms'],
            ['mail', 'sms', 'whatsapp'],
        ];

        // Act & Assert
        foreach ($channelsList as $channels) {
            $attributes = array_merge($this->validAttributes, ['channels' => $channels]);
            $otp = OneTimePassword::create($attributes);

            $this->assertEquals($channels, $otp->channels);
            $this->assertDatabaseHas('one_time_passwords', ['id' => $otp->id]);
        }
    }

    /**
     * Test that meta data can be stored as JSON.
     */
    public function test_meta_data_can_be_stored_as_json(): void
    {
        // Arrange
        $metaList = [
            ['ip' => '127.0.0.1'],
            ['ip' => '192.168.1.1', 'user_agent' => 'Mozilla/5.0'],
            ['custom_field' => 'value', 'another' => 123],
            null,
        ];

        // Act & Assert
        foreach ($metaList as $meta) {
            $attributes = array_merge($this->validAttributes, ['meta' => $meta]);
            $otp = OneTimePassword::create($attributes);

            $this->assertEquals($meta, $otp->meta);
            $this->assertDatabaseHas('one_time_passwords', ['id' => $otp->id]);
        }
    }
}
