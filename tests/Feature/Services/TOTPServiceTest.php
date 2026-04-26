<?php

declare(strict_types=1);

namespace Kani\Mfa\Tests\Feature\Services;

use Kani\Mfa\Tests\TestCase;
use Kani\Mfa\Totp\Services\TOTPService;
use OTPHP\Exception\SecretDecodingException;
use ParagonIE\ConstantTime\Base32;

/**
 * Test suite for TOTPService core functionality.
 *
 * Validates TOTP operations including secret generation,
 * code verification, current code generation, and edge cases.
 */
final class TOTPServiceTest extends TestCase
{
    private TOTPService $totpService;

    private string $testSecret;

    /**
     * Setup test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create TOTP service with default configuration
        $this->totpService = new TOTPService(
            period: 30,
            digits: 6,
            algorithm: 'sha1',
            window: 1
        );

        // Arrange: Generate a test secret
        $this->testSecret = $this->totpService->generateSecret();
    }

    /**
     * Test that generateSecret returns a valid Base32 encoded string.
     */
    public function test_generate_secret_returns_valid_base32_string(): void
    {
        // Arrange: Nothing specific to arrange

        // Act: Generate a secret
        $secret = $this->totpService->generateSecret();

        // Assert: Secret is not empty
        $this->assertNotEmpty($secret);

        // Assert: Secret contains only uppercase Base32 characters (A-Z, 2-7)
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);

        // Assert: Secret can be decoded without errors
        try {
            $decoded = Base32::decodeUpper($secret);
            $this->assertIsString($decoded);
            $this->assertEquals(20, strlen($decoded)); // 20 bytes = 160 bits
        } catch (\Exception $e) {
            $this->fail('Secret should be valid Base32: '.$e->getMessage());
        }
    }

    /**
     * Test that generateSecret produces unique values on each call.
     */
    public function test_generate_secret_produces_unique_values(): void
    {
        // Arrange: Nothing specific to arrange

        // Act: Generate multiple secrets
        $secret1 = $this->totpService->generateSecret();
        $secret2 = $this->totpService->generateSecret();
        $secret3 = $this->totpService->generateSecret();

        // Assert: All secrets are different
        $this->assertNotEquals($secret1, $secret2);
        $this->assertNotEquals($secret1, $secret3);
        $this->assertNotEquals($secret2, $secret3);
    }

    /**
     * Test that generateSecret produces 32 character Base32 strings.
     */
    public function test_generate_secret_produces_expected_length(): void
    {
        // Arrange: Nothing specific to arrange

        // Act: Generate a secret
        $secret = $this->totpService->generateSecret();

        // Assert: Secret length should be 32 characters (20 bytes encoded in Base32)
        $this->assertEquals(32, strlen($secret));
    }

    /**
     * Test that now returns a 6-digit code for a valid secret.
     */
    public function test_now_returns_six_digit_code(): void
    {
        // Arrange: Valid secret already available

        // Act: Get current TOTP code
        $code = $this->totpService->now($this->testSecret);

        // Assert: Code is 6 digits
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertEquals(6, strlen($code));
    }

    /**
     * Test that now returns a numeric string.
     */
    public function test_now_returns_numeric_string(): void
    {
        // Arrange: Valid secret already available

        // Act: Get current TOTP code
        $code = $this->totpService->now($this->testSecret);

        // Assert: Code contains only digits
        $this->assertTrue(ctype_digit($code));
    }

    /**
     * Test that now with timestamp parameter returns a code for specific time.
     */
    public function test_now_with_timestamp_returns_code_for_specific_time(): void
    {
        // Arrange: Define a specific timestamp
        $timestamp = 100000;

        // Act: Generate code for specific timestamp
        $code = $this->totpService->now($this->testSecret, $timestamp);

        // Assert: Returns a 6-digit code
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // Act: Verify the same code works with that timestamp
        $isValid = $this->totpService->verify($this->testSecret, $code, null, $timestamp);

        // Assert: Verification succeeds
        $this->assertTrue($isValid);
    }

    /**
     * Test that now returns a valid TOTP code that can be verified.
     */
    public function test_now_returns_code_that_can_be_verified(): void
    {
        // Arrange: Valid secret already available

        // Act: Get current code and verify it
        $code = $this->totpService->now($this->testSecret);
        $isValid = $this->totpService->verify($this->testSecret, $code);

        // Assert: Code is valid
        $this->assertTrue($isValid);
    }

    /**
     * Test that verify returns true for a valid TOTP code.
     */
    public function test_verify_returns_true_for_valid_code(): void
    {
        // Arrange: Get a valid code
        $validCode = $this->totpService->now($this->testSecret);

        // Act: Verify the code
        $result = $this->totpService->verify($this->testSecret, $validCode);

        // Assert: Verification succeeds
        $this->assertTrue($result);
    }

    /**
     * Test that verify returns false for an invalid TOTP code.
     */
    public function test_verify_returns_false_for_invalid_code(): void
    {
        // Arrange: Invalid code
        $invalidCode = '123456';

        // Act: Verify with wrong code
        $result = $this->totpService->verify($this->testSecret, $invalidCode);

        // Assert: Verification fails
        $this->assertFalse($result);
    }

    /**
     * Test that verify respects a custom verification window.
     */
    public function test_verify_respects_custom_window(): void
    {
        // Arrange: Get a code from the TOTP service
        $code = $this->totpService->now($this->testSecret);

        // Act: Verify with custom windows
        $resultWindow0 = $this->totpService->verify($this->testSecret, $code, 0);
        $resultWindow1 = $this->totpService->verify($this->testSecret, $code, 1);
        $resultWindow2 = $this->totpService->verify($this->testSecret, $code, 2);

        // Assert: Current code should work with any window size
        $this->assertTrue($resultWindow0);
        $this->assertTrue($resultWindow1);
        $this->assertTrue($resultWindow2);
    }

    /**
     * Test that verify fails for a code from a different secret.
     */
    public function test_verify_fails_for_code_from_different_secret(): void
    {
        // Arrange: Generate another secret and get its current code
        $otherSecret = $this->totpService->generateSecret();
        $codeFromOtherSecret = $this->totpService->now($otherSecret);

        // Act: Verify the other secret's code against the original secret
        $result = $this->totpService->verify($this->testSecret, $codeFromOtherSecret);

        // Assert: Verification fails
        $this->assertFalse($result);
    }

    /**
     * Test that verify works with custom service parameters.
     */
    public function test_verify_works_with_custom_parameters(): void
    {
        // Arrange: Create service with custom parameters
        $customService = new TOTPService(
            period: 60,
            digits: 8,
            algorithm: 'sha256',
            window: 2
        );

        $secret = $customService->generateSecret();

        // Act: Get and verify a code
        $code = $customService->now($secret);
        $isValid = $customService->verify($secret, $code);

        // Assert: Verification succeeds
        $this->assertTrue($isValid);

        // Assert: Code has 8 digits
        $this->assertEquals(8, strlen($code));
    }

    /**
     * Test that verify with window parameter overrides the default window.
     */
    public function test_verify_window_parameter_overrides_default(): void
    {
        // Arrange: Create service with default window = 0
        $strictService = new TOTPService(window: 0);
        $secret = $strictService->generateSecret();

        // Get the current code
        $currentCode = $strictService->now($secret);

        // Act: Verify with explicit window parameter
        $resultWithDefault = $strictService->verify($secret, $currentCode);
        $resultWithExplicitWindow = $strictService->verify($secret, $currentCode, 1);

        // Assert: Default window (0) should verify current code
        $this->assertTrue($resultWithDefault);

        // Assert: Explicit window should also verify
        $this->assertTrue($resultWithExplicitWindow);
    }

    /**
     * Test that verify handles empty code gracefully.
     */
    public function test_verify_handles_empty_code_gracefully(): void
    {
        // Arrange & Act: Verify with empty string
        $resultEmpty = $this->totpService->verify($this->testSecret, '');

        // Act: Verify with null-like input
        $resultNull = $this->totpService->verify($this->testSecret, 'null');

        // Assert: Both fail
        $this->assertFalse($resultEmpty);
        $this->assertFalse($resultNull);
    }

    /**
     * Test that verify handles a code that is too short gracefully.
     */
    public function test_verify_handles_short_code_gracefully(): void
    {
        // Arrange & Act: Verify with a 5-digit code
        $result = $this->totpService->verify($this->testSecret, '12345');

        // Assert: Should return false
        $this->assertFalse($result);
    }

    /**
     * Test that verify handles a code that is too long gracefully.
     */
    public function test_verify_handles_long_code_gracefully(): void
    {
        // Arrange & Act: Verify with a 7-digit code
        $result = $this->totpService->verify($this->testSecret, '1234567');

        // Assert: Should return false
        $this->assertFalse($result);
    }

    /**
     * Test that verify handles invalid secret by throwing an exception.
     */
    public function test_verify_throws_exception_for_invalid_secret(): void
    {
        // Arrange: Invalid Base32 secret
        $invalidSecret = 'INVALID_SECRET!!!';
        $code = '123456';

        // Expect an exception to be thrown
        $this->expectException(SecretDecodingException::class);

        // Act: Try to verify with an invalid Base32 secret
        $this->totpService->verify($invalidSecret, $code);
    }

    /**
     * Test that TOTPService can be instantiated with default parameters.
     */
    public function test_can_be_instantiated_with_default_parameters(): void
    {
        // Act: Create service with no parameters
        $service = new TOTPService;

        // Arrange: Generate secret and code
        $secret = $service->generateSecret();
        $code = $service->now($secret);

        // Assert: Default parameters work correctly
        $this->assertTrue($service->verify($secret, $code));
        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    /**
     * Test that TOTPService supports different hash algorithms.
     */
    public function test_supports_different_hash_algorithms(): void
    {
        // Arrange: Test secret
        $secret = $this->totpService->generateSecret();

        // Act: Create services with different algorithms
        $sha1Service = new TOTPService(algorithm: 'sha1');
        $sha256Service = new TOTPService(algorithm: 'sha256');
        $sha512Service = new TOTPService(algorithm: 'sha512');

        $sha1Code = $sha1Service->now($secret);
        $sha256Code = $sha256Service->now($secret);
        $sha512Code = $sha512Service->now($secret);

        // Assert: All services produce valid codes
        $this->assertTrue($sha1Service->verify($secret, $sha1Code));
        $this->assertTrue($sha256Service->verify($secret, $sha256Code));
        $this->assertTrue($sha512Service->verify($secret, $sha512Code));
    }

    /**
     * Test that TOTPService handles different digit lengths.
     */
    public function test_supports_different_digit_lengths(): void
    {
        // Arrange: Create services with different digit lengths
        $service6Digits = new TOTPService(digits: 6);
        $service8Digits = new TOTPService(digits: 8);
        $secret = $service6Digits->generateSecret();

        // Act: Get codes
        $code6Digits = $service6Digits->now($secret);
        $code8Digits = $service8Digits->now($secret);

        // Assert: Correct digit lengths
        $this->assertEquals(6, strlen($code6Digits));
        $this->assertEquals(8, strlen($code8Digits));

        // Assert: Both verify correctly
        $this->assertTrue($service6Digits->verify($secret, $code6Digits));
        $this->assertTrue($service8Digits->verify($secret, $code8Digits));
    }

    /**
     * Test that TOTPService handles different time periods.
     */
    public function test_supports_different_time_periods(): void
    {
        // Arrange: Create services with different periods
        $service30Sec = new TOTPService(period: 30);
        $service60Sec = new TOTPService(period: 60);
        $secret = $service30Sec->generateSecret();

        // Act: Get codes
        $code30Sec = $service30Sec->now($secret);
        $code60Sec = $service60Sec->now($secret);

        // Assert: Both verify correctly with their respective periods
        $this->assertTrue($service30Sec->verify($secret, $code30Sec));
        $this->assertTrue($service60Sec->verify($secret, $code60Sec));

        // Note: Codes from different periods will be different
        $this->assertNotEquals($code30Sec, $code60Sec);
    }

    /**
     * Test that the service properties are readonly.
     */
    public function test_service_properties_are_readonly(): void
    {
        // Arrange: Create service with custom values
        $service = new TOTPService(period: 45, digits: 7, algorithm: 'sha512', window: 3);

        // Act: Get properties via reflection
        $reflection = new \ReflectionClass($service);
        $periodProperty = $reflection->getProperty('period');
        $digitsProperty = $reflection->getProperty('digits');
        $algorithmProperty = $reflection->getProperty('algorithm');
        $windowProperty = $reflection->getProperty('window');

        // Assert: All properties are readonly
        $this->assertTrue($periodProperty->isReadOnly());
        $this->assertTrue($digitsProperty->isReadOnly());
        $this->assertTrue($algorithmProperty->isReadOnly());
        $this->assertTrue($windowProperty->isReadOnly());
    }

    /**
     * Test that the same code can be verified multiple times within the same period.
     */
    public function test_code_verification_repeats_until_period_expires(): void
    {
        // Arrange: Get current code
        $code = $this->totpService->now($this->testSecret);

        // Act: Verify multiple times
        $firstVerify = $this->totpService->verify($this->testSecret, $code);
        $secondVerify = $this->totpService->verify($this->testSecret, $code);
        $thirdVerify = $this->totpService->verify($this->testSecret, $code);

        // Assert: All verifications succeed
        $this->assertTrue($firstVerify);
        $this->assertTrue($secondVerify);
        $this->assertTrue($thirdVerify);
    }

    /**
     * Test that verify with window > 0 allows codes from previous periods.
     */
    public function test_verify_with_window_allows_previous_period_codes(): void
    {
        // Arrange: Create service with window = 1
        $serviceWithWindow = new TOTPService(window: 1);
        $secret = $serviceWithWindow->generateSecret();

        // Get code from previous period using timestamp parameter
        $previousTimestamp = 100000;
        $previousCode = $serviceWithWindow->now($secret, $previousTimestamp);

        // Current timestamp (next period)
        $currentTimestamp = 100030;

        // Act: Verify previous period code with window = 1 at current timestamp
        $isValid = $serviceWithWindow->verify($secret, $previousCode, 1, $currentTimestamp);

        // Assert: Code from previous period is accepted (window=1 allows ±1 period)
        $this->assertTrue($isValid);
    }

    /**
     * Test that verify with window = 0 rejects codes from previous periods.
     */
    public function test_verify_with_window_zero_rejects_previous_period_codes(): void
    {
        // Arrange: Create service with window = 0
        $strictService = new TOTPService(window: 0);
        $secret = $strictService->generateSecret();

        // Define timestamps
        $timestamp1 = 100000; // Period 1 (floor 100000/30 = 3333)
        $timestamp2 = 100030; // Period 2 (floor 100030/30 = 3334)

        // Get code at timestamp1
        $codeAtTime1 = $strictService->now($secret, $timestamp1);

        // Get code at timestamp2
        $codeAtTime2 = $strictService->now($secret, $timestamp2);

        // Assert: Code works at same timestamp with window=0
        $isValidAtSameTime = $strictService->verify($secret, $codeAtTime1, 0, $timestamp1);
        $this->assertTrue($isValidAtSameTime, 'Code should work at the same timestamp with window=0');

        // Act: Try to verify the same code at timestamp2 with window=0
        $isValidAtNextPeriod = $strictService->verify($secret, $codeAtTime1, 0, $timestamp2);

        // Assert: Code from previous period should be rejected with window=0
        $this->assertFalse($isValidAtNextPeriod, 'Code from previous period should be rejected when window=0');

        // Assert: Old code works with window=1
        $isValidWithWindow1 = $strictService->verify($secret, $codeAtTime1, 1, $timestamp2);
        $this->assertTrue($isValidWithWindow1, 'Old code should work with window=1');

        // Assert: New code works at its timestamp with window=0
        $isValidNewCode = $strictService->verify($secret, $codeAtTime2, 0, $timestamp2);
        $this->assertTrue($isValidNewCode, 'Current period code should work with window=0');
    }

    /**
     * Test that verify with timestamp parameter works correctly.
     */
    public function test_verify_with_timestamp_parameter(): void
    {
        // Arrange: Create service and secret
        $service = new TOTPService;
        $secret = $service->generateSecret();

        $timestamp = 100000;
        $code = $service->now($secret, $timestamp);

        // Act: Verify code at the same timestamp
        $isValid = $service->verify($secret, $code, null, $timestamp);

        // Assert: Verification succeeds
        $this->assertTrue($isValid);

        // Act: Verify same code at different timestamp
        $isValidAtDifferentTime = $service->verify($secret, $code, 0, $timestamp + 60);

        // Assert: Should fail because window=0 and time is different
        $this->assertFalse($isValidAtDifferentTime);
    }
}
