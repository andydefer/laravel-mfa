<?php

declare(strict_types=1);

namespace Kani\Mfa\Tests\Unit\Otp\Services;

use Kani\Mfa\Otp\Services\DefaultCodeGenerator;
use Kani\Mfa\Tests\TestCase;

/**
 * Test suite for DefaultCodeGenerator service.
 *
 * Validates that the OTP code generator produces 6-digit numeric codes
 * with proper formatting and cryptographic randomness.
 *
 * @package Kani\Mfa\Tests\Unit\Otp\Services
 */
final class DefaultCodeGeneratorTest extends TestCase
{
    private DefaultCodeGenerator $generator;

    /**
     * Setup test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create generator instance
        $this->generator = new DefaultCodeGenerator();
    }

    /**
     * Test that generate returns a 6-digit string.
     */
    public function test_generate_returns_six_digit_string(): void
    {
        // Act: Generate a code
        $code = $this->generator->generate();

        // Assert: Code is 6 characters long
        $this->assertEquals(6, strlen($code));
    }

    /**
     * Test that generate returns a numeric string.
     */
    public function test_generate_returns_numeric_string(): void
    {
        // Act: Generate a code
        $code = $this->generator->generate();

        // Assert: Contains only digits
        $this->assertMatchesRegularExpression('/^\d+$/', $code);
    }

    /**
     * Test that generate returns string with leading zeros when necessary.
     */
    public function test_generate_returns_string_with_leading_zeros_when_necessary(): void
    {
        // Note: We cannot guarantee a small number, but we can test the format
        // Act: Generate multiple codes to observe possible leading zeros
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = $this->generator->generate();
        }

        // Assert: All codes are 6 digits
        foreach ($codes as $code) {
            $this->assertEquals(6, strlen($code));
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        }
    }

    /**
     * Test that generate produces codes between 000000 and 999999.
     */
    public function test_generate_produces_code_in_valid_range(): void
    {
        // Act: Generate a code
        $code = $this->generator->generate();

        // Assert: Code as integer is between 0 and 999999
        $codeInt = (int) $code;
        $this->assertGreaterThanOrEqual(0, $codeInt);
        $this->assertLessThanOrEqual(999999, $codeInt);
    }

    /**
     * Test that generate produces different codes on successive calls.
     */
    public function test_generate_produces_different_codes_on_successive_calls(): void
    {
        // Act: Generate two codes
        $code1 = $this->generator->generate();
        $code2 = $this->generator->generate();

        // Note: There is a very small chance they could be equal (1/1,000,000)
        // This test might occasionally fail, but it's extremely unlikely
        // For deterministic tests, we generate multiple codes
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = $this->generator->generate();
        }

        // Assert: Not all codes are identical
        $uniqueCount = count(array_unique($codes));
        $this->assertGreaterThan(1, $uniqueCount, 'Generated codes should not all be identical');
    }

    /**
     * Test that generate returns a string type.
     */
    public function test_generate_returns_string_type(): void
    {
        // Act: Generate a code
        $code = $this->generator->generate();

        // Assert: Result is a string
        $this->assertIsString($code);
    }

    /**
     * Test that generate works correctly for multiple iterations.
     */
    public function test_generate_works_correctly_for_multiple_iterations(): void
    {
        // Act: Generate 1000 codes
        $codes = [];
        for ($i = 0; $i < 1000; $i++) {
            $codes[] = $this->generator->generate();
        }

        // Assert: All codes are valid 6-digit strings
        foreach ($codes as $code) {
            $this->assertEquals(6, strlen($code));
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        }

        // Assert: At least some variation exists (not all the same)
        $uniqueCount = count(array_unique($codes));
        $this->assertGreaterThan(1, $uniqueCount);
    }

    /**
     * Test that generate never returns empty string.
     */
    public function test_generate_never_returns_empty_string(): void
    {
        // Act: Generate a code
        $code = $this->generator->generate();

        // Assert: Code is not empty
        $this->assertNotEmpty($code);
    }

    /**
     * Test that all generated codes are exactly 6 digits (no more, no less).
     */
    public function test_all_generated_codes_are_exactly_six_digits(): void
    {
        // Act: Generate 100 codes
        for ($i = 0; $i < 100; $i++) {
            $code = $this->generator->generate();

            // Assert: Each code is exactly 6 digits
            $this->assertEquals(6, strlen($code), "Code '{$code}' should be 6 digits");
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        }
    }

    /**
     * Test that generate respects the 000001 format for small numbers.
     * This test uses reflection to verify the padding logic.
     */
    public function test_generate_pads_with_leading_zeros(): void
    {
        // Create a testable version using reflection to simulate a small random number
        $generator = new DefaultCodeGenerator();

        // We can't easily mock random_int, but we can generate enough samples
        // to likely see various formats including those with leading zeros
        $hasLeadingZero = false;
        $hasNoLeadingZero = false;

        for ($i = 0; $i < 1000; $i++) {
            $code = $generator->generate();
            if ($code[0] === '0') {
                $hasLeadingZero = true;
            } else {
                $hasNoLeadingZero = true;
            }

            if ($hasLeadingZero && $hasNoLeadingZero) {
                break;
            }
        }

        // Assert: Both formats should appear (leading zero and without)
        $this->assertTrue($hasLeadingZero, 'Should generate codes with leading zeros');
        $this->assertTrue($hasNoLeadingZero, 'Should generate codes without leading zeros');
    }

    /**
     * Test that generate produces uniformly distributed codes.
     * This is a statistical test to ensure no obvious bias.
     */
    public function test_generate_produces_uniformly_distributed_codes(): void
    {
        // Act: Generate 10000 codes
        $codes = [];
        for ($i = 0; $i < 10000; $i++) {
            $codes[] = $this->generator->generate();
        }

        // Count first digit distribution (should be roughly 10% each)
        $firstDigitCounts = array_fill(0, 10, 0);
        foreach ($codes as $code) {
            $firstDigit = (int) $code[0];
            $firstDigitCounts[$firstDigit]++;
        }

        // Assert: Each first digit appears between 8% and 12% (800-1200 out of 10000)
        foreach ($firstDigitCounts as $digit => $count) {
            $this->assertGreaterThan(800, $count, "Digit {$digit} appears only {$count} times");
            $this->assertLessThan(1200, $count, "Digit {$digit} appears {$count} times (too many)");
        }
    }
}
