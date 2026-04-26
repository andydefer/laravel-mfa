<?php
// src/Contracts/Otp/RateLimiterInterface.php

declare(strict_types=1);

namespace Kani\Mfa\Otp\Contracts;

/**
 * Contract for rate limiting OTP operations.
 *
 * This abstraction allows different rate limiting strategies to be
 * injected into the OtpService, improving testability and flexibility.
 */
interface RateLimiterInterface
{
    /**
     * Check if the rate limit has been exceeded for a given key.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @param int $maxAttempts Maximum allowed attempts
     * @return bool True if rate limit is exceeded
     */
    public function isExceeded(string $key, int $maxAttempts): bool;

    /**
     * Record an attempt for a given key.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @param int $decaySeconds Number of seconds the rate limit window lasts
     */
    public function hit(string $key, int $decaySeconds): void;

    /**
     * Get the number of seconds until the rate limit resets.
     *
     * @param string $key Unique identifier for the rate limit bucket
     * @return int Seconds until reset
     */
    public function getAvailableInSeconds(string $key): int;

    /**
     * Clear the rate limit for a given key.
     *
     * @param string $key Unique identifier for the rate limit bucket
     */
    public function clear(string $key): void;
}
