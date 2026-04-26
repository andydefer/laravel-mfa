<?php

declare(strict_types=1);

namespace Kani\Mfa\Tests\Unit\Services;

use Illuminate\Support\Facades\RateLimiter;
use Kani\Mfa\Otp\Services\LaravelRateLimiter;
use Kani\Mfa\Tests\TestCase;

/**
 * Test suite for LaravelRateLimiter service.
 *
 * Validates that the LaravelRateLimiter correctly delegates operations
 * to Laravel's RateLimiter facade and properly handles edge cases.
 *
 * @package Kani\Mfa\Tests\Unit\Otp\Services
 */
final class LaravelRateLimiterTest extends TestCase
{
    private LaravelRateLimiter $rateLimiter;
    private string $testKey;

    /**
     * Setup test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create service instance and test key
        $this->rateLimiter = new LaravelRateLimiter();
        $this->testKey = 'test_rate_limit_key_' . uniqid();
    }

    /**
     * Test that isExceeded returns false when limit is not reached.
     */
    public function test_is_exceeded_returns_false_when_limit_not_reached(): void
    {
        // Act: Check if rate limit is exceeded (no hits yet)
        $isExceeded = $this->rateLimiter->isExceeded($this->testKey, 3);

        // Assert: Should return false
        $this->assertFalse($isExceeded);
    }

    /**
     * Test that isExceeded returns true when limit is reached.
     */
    public function test_is_exceeded_returns_true_when_limit_reached(): void
    {
        // Arrange: Hit the rate limiter 3 times (reaching the limit)
        for ($i = 0; $i < 3; $i++) {
            $this->rateLimiter->hit($this->testKey, 60);
        }

        // Act: Check if rate limit is exceeded
        $isExceeded = $this->rateLimiter->isExceeded($this->testKey, 3);

        // Assert: Should return true
        $this->assertTrue($isExceeded);
    }

    /**
     * Test that isExceeded returns false after limit is cleared.
     */
    public function test_is_exceeded_returns_false_after_clear(): void
    {
        // Arrange: Hit the rate limiter 3 times (reaching the limit)
        for ($i = 0; $i < 3; $i++) {
            $this->rateLimiter->hit($this->testKey, 60);
        }

        // Verify limit is exceeded
        $this->assertTrue($this->rateLimiter->isExceeded($this->testKey, 3));

        // Act: Clear the rate limit
        $this->rateLimiter->clear($this->testKey);

        // Assert: Should return false after clear
        $this->assertFalse($this->rateLimiter->isExceeded($this->testKey, 3));
    }

    /**
     * Test that hit increments the attempt counter.
     */
    public function test_hit_increments_attempt_counter(): void
    {
        // Arrange: Initial state - no hits
        $isExceededBefore = $this->rateLimiter->isExceeded($this->testKey, 2);

        // Act: First hit
        $this->rateLimiter->hit($this->testKey, 60);

        // Assert: After first hit, limit should not be exceeded (2 attempts needed)
        $isExceededAfterFirst = $this->rateLimiter->isExceeded($this->testKey, 2);
        $this->assertFalse($isExceededBefore);
        $this->assertFalse($isExceededAfterFirst);

        // Act: Second hit
        $this->rateLimiter->hit($this->testKey, 60);

        // Assert: After second hit, limit should be exceeded
        $isExceededAfterSecond = $this->rateLimiter->isExceeded($this->testKey, 2);
        $this->assertTrue($isExceededAfterSecond);
    }

    /**
     * Test that getAvailableInSeconds returns correct remaining time.
     */
    public function test_get_available_in_seconds_returns_correct_time(): void
    {
        // Arrange: Hit the rate limiter once
        $this->rateLimiter->hit($this->testKey, 60);

        // Act: Get available time
        $availableIn = $this->rateLimiter->getAvailableInSeconds($this->testKey);

        // Assert: Should be less than or equal to decay seconds (60)
        $this->assertLessThanOrEqual(60, $availableIn);
        $this->assertGreaterThanOrEqual(0, $availableIn);
    }

    /**
     * Test that getAvailableInSeconds returns 0 when limit is not reached.
     */
    public function test_get_available_in_seconds_returns_zero_when_limit_not_reached(): void
    {
        // Arrange: No hits yet
        // Act: Get available time
        $availableIn = $this->rateLimiter->getAvailableInSeconds($this->testKey);

        // Assert: Should be 0 because no limit is set
        $this->assertEquals(0, $availableIn);
    }

    /**
     * Test that clear removes all rate limit records.
     */
    public function test_clear_removes_rate_limit_records(): void
    {
        // Arrange: Create a rate limit by hitting 3 times
        for ($i = 0; $i < 3; $i++) {
            $this->rateLimiter->hit($this->testKey, 60);
        }

        // Verify limit is reached
        $this->assertTrue($this->rateLimiter->isExceeded($this->testKey, 3));

        // Act: Clear the rate limit
        $this->rateLimiter->clear($this->testKey);

        // Assert: Limit should no longer be exceeded
        $this->assertFalse($this->rateLimiter->isExceeded($this->testKey, 3));

        // Assert: Available time should be 0
        $this->assertEquals(0, $this->rateLimiter->getAvailableInSeconds($this->testKey));
    }

    /**
     * Test that different keys have independent rate limits.
     */
    public function test_different_keys_have_independent_rate_limits(): void
    {
        // Arrange: Two different keys
        $key1 = 'key1_' . uniqid();
        $key2 = 'key2_' . uniqid();

        // Act: Hit only key1
        for ($i = 0; $i < 3; $i++) {
            $this->rateLimiter->hit($key1, 60);
        }

        // Assert: Key1 should be exceeded, key2 should not
        $this->assertTrue($this->rateLimiter->isExceeded($key1, 3));
        $this->assertFalse($this->rateLimiter->isExceeded($key2, 3));
    }

    /**
     * Test that hit respects the decay seconds parameter.
     */
    public function test_hit_respects_decay_seconds_parameter(): void
    {
        // Arrange: Hit with a very short decay (1 second)
        $this->rateLimiter->hit($this->testKey, 1);

        // Assert: Immediately after hit, limit should be tracked
        $isExceeded = $this->rateLimiter->isExceeded($this->testKey, 1);
        $this->assertTrue($isExceeded);

        // Wait for the decay to expire
        $this->travel(2)->seconds();

        // Assert: After decay, limit should no longer be exceeded
        $isExceededAfterDecay = $this->rateLimiter->isExceeded($this->testKey, 1);
        $this->assertFalse($isExceededAfterDecay);
    }

    /**
     * Test that clear on non-existent key does not throw exception.
     */
    public function test_clear_on_non_existent_key_does_not_throw_exception(): void
    {
        // Arrange: Non-existent key
        $nonExistentKey = 'non_existent_' . uniqid();

        // Act & Assert: Clear should not throw exception
        $this->rateLimiter->clear($nonExistentKey);
        $this->assertTrue(true);
    }

    /**
     * Test that getAvailableInSeconds on non-existent key returns 0.
     */
    public function test_get_available_in_seconds_on_non_existent_key_returns_zero(): void
    {
        // Arrange: Non-existent key
        $nonExistentKey = 'non_existent_' . uniqid();

        // Act: Get available time
        $availableIn = $this->rateLimiter->getAvailableInSeconds($nonExistentKey);

        // Assert: Should be 0
        $this->assertEquals(0, $availableIn);
    }

    /**
     * Test that multiple hits accumulate correctly.
     */
    public function test_multiple_hits_accumulate_correctly(): void
    {
        // Arrange: Hit 5 times with max attempts 3
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->hit($this->testKey, 60);
        }

        // Assert: Should be exceeded
        $this->assertTrue($this->rateLimiter->isExceeded($this->testKey, 3));

        // Assert: Available time should be > 0
        $availableIn = $this->rateLimiter->getAvailableInSeconds($this->testKey);
        $this->assertGreaterThan(0, $availableIn);
    }
}
