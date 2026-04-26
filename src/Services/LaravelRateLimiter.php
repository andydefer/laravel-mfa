<?php

declare(strict_types=1);

namespace Kani\Otp\Services;

use Illuminate\Support\Facades\RateLimiter;
use Kani\Otp\Contracts\RateLimiterInterface;

/**
 * Laravel implementation of the rate limiter interface.
 *
 * This class acts as an adapter between the package's rate limiting contract
 * and Laravel's built-in RateLimiter facade. It delegates all operations to
 * Laravel's rate limiter, providing methods to check limits, record hits,
 * get wait times, and clear rate limits.
 */
final class LaravelRateLimiter implements RateLimiterInterface
{
    /**
     * Check if the rate limit has been exceeded for a given key.
     *
     * @param string $key Unique identifier for the rate-limited action
     * @param int $maxAttempts Maximum number of attempts allowed
     * @return bool True if the maximum attempts have been exceeded
     */
    public function isExceeded(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts(
            key: $key,
            maxAttempts: $maxAttempts
        );
    }

    /**
     * Record a hit for the given rate-limited key.
     *
     * Increases the attempt counter for the key and sets the expiration
     * time for the rate limit window.
     *
     * @param string $key Unique identifier for the rate-limited action
     * @param int $decaySeconds Number of seconds until the hit decays
     */
    public function hit(string $key, int $decaySeconds): void
    {
        RateLimiter::hit(
            key: $key,
            decaySeconds: $decaySeconds
        );
    }

    /**
     * Get the number of seconds until the rate limit resets for a key.
     *
     * Useful for informing clients how long they need to wait before
     * attempting the action again.
     *
     * @param string $key Unique identifier for the rate-limited action
     * @return int Number of seconds remaining until the limit resets
     */
    public function getAvailableInSeconds(string $key): int
    {
        return RateLimiter::availableIn(
            key: $key
        );
    }

    /**
     * Clear all rate limit records for a given key.
     *
     * Resets the attempt counter and removes any stored data for the key,
     * effectively removing all rate limits for that identifier.
     *
     * @param string $key Unique identifier for the rate-limited action
     */
    public function clear(string $key): void
    {
        RateLimiter::clear(
            key: $key
        );
    }
}
