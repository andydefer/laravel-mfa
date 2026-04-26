<?php

declare(strict_types=1);

// config/otp.php

/**
 * OTP (One-Time Password) Package Configuration.
 *
 * This configuration file controls all aspects of the OTP package behavior:
 * - Default OTP settings (expiry, attempts)
 * - Automatic cleanup of expired/used OTPs
 * - Security rate limiting to prevent abuse
 * - Localization settings for multilingual support
 *
 * All settings can be overridden via environment variables using the
 * OTP_* prefix as documented in each section.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Localization Settings
    |--------------------------------------------------------------------------
    |
    | Configure language preferences for OTP notifications and user-facing
    | messages. The package ships with French and English translations.
    |
    */

    'localization' => [

        /**
         * Default locale for OTP messages.
         *
         * Determines which language is used for all OTP notifications.
         * Supported locales: 'fr' (French), 'en' (English)
         * Override via OTP_LOCALE environment variable.
         *
         * @var string
         */
        'locale' => env('OTP_LOCALE', 'en'),

        /**
         * Supported locales available in the package.
         *
         * Languages that have complete translation files.
         * Add new locales here when translations become available.
         *
         * @var array<int, string>
         */
        'supported_locales' => [
            'fr', // French
            'en', // English
        ],

        /**
         * Fallback locale when translation is missing.
         *
         * If a translation key doesn't exist in the configured locale,
         * the package will fall back to this locale as a last resort.
         *
         * @var string
         */
        'fallback_locale' => env('OTP_FALLBACK_LOCALE', 'en'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Default OTP Settings
    |--------------------------------------------------------------------------
    |
    | These values define the standard behavior for all new OTPs unless
    | explicitly overridden at creation time.
    |
    */

    /**
     * Default lifetime of an OTP in minutes.
     *
     * After this period, the OTP expires and cannot be used for verification.
     * Override via OTP_DEFAULT_EXPIRY_MINUTES environment variable.
     *
     * @var int
     */
    'default_expiry_minutes' => env('OTP_DEFAULT_EXPIRY_MINUTES', 10),

    /**
     * Default maximum number of verification attempts allowed.
     *
     * Prevents brute-force attacks by limiting how many times an OTP
     * can be tried before being automatically invalidated.
     * Override via OTP_DEFAULT_MAX_ATTEMPTS environment variable.
     *
     * @var int
     */
    'default_max_attempts' => env('OTP_DEFAULT_MAX_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Automatic garbage collection for old OTP records to maintain
    | database performance and compliance with data retention policies.
    |
    */

    'cleanup' => [

        /**
         * Enable automatic cleanup of expired/used OTPs.
         *
         * When true, old records are automatically removed from the database.
         * When false, manual cleanup is required via the cleanup command.
         * Override via OTP_AUTO_CLEANUP environment variable.
         *
         * @var bool
         */
        'auto_cleanup' => env('OTP_AUTO_CLEANUP', true),

        /**
         * Cleanup execution frequency in minutes.
         *
         * How often the scheduled cleanup job should run when auto_cleanup is enabled.
         * Override via OTP_CLEANUP_FREQUENCY environment variable.
         *
         * @var int
         */
        'frequency' => env('OTP_CLEANUP_FREQUENCY', 60),

        /**
         * Number of days to retain OTP records.
         *
         * OTPs older than this value are considered stale and eligible for cleanup,
         * regardless of their current status (expired, used, verified, etc.).
         * Override via OTP_RETENTION_DAYS environment variable.
         *
         * @var int
         */
        'retention_days' => env('OTP_RETENTION_DAYS', 30),

    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration to prevent OTP abuse and brute-force attacks.
    | All limits are applied per OTP type and per destination (email/phone).
    |
    */

    'security' => [

        /**
         * Maximum OTP generation requests per time window.
         *
         * Limits how many OTPs a user can request within the rate limit window
         * to prevent SMS/email bombing attacks.
         * Override via OTP_RATE_LIMIT_REQUESTS environment variable.
         *
         * @var int
         */
        'rate_limit_requests' => env('OTP_RATE_LIMIT_REQUESTS', 3),

        /**
         * Maximum OTP verification attempts per time window.
         *
         * Limits how many failed verification attempts are allowed before
         * blocking further attempts, preventing brute-force attacks.
         * Override via OTP_RATE_LIMIT_VERIFICATIONS environment variable.
         *
         * @var int
         */
        'rate_limit_verifications' => env('OTP_RATE_LIMIT_VERIFICATIONS', 5),

        /**
         * Rate limit time window in minutes.
         *
         * The duration during which the rate limits (requests and verifications)
         * are enforced. After this period, the counters are reset.
         * Override via OTP_RATE_LIMIT_DECAY_MINUTES environment variable.
         *
         * @var int
         */
        'rate_limit_decay_minutes' => env('OTP_RATE_LIMIT_DECAY_MINUTES', 60),

        /**
         * Decay time for failed verification attempts in seconds.
         *
         * How long to block verification attempts after a failure.
         * Override via OTP_FAILED_VERIFICATION_DECAY_SECONDS environment variable.
         *
         * @var int
         */
        'failed_verification_decay_seconds' => env('OTP_FAILED_VERIFICATION_DECAY_SECONDS', 300),

        /**
         * Decay time for rate limit hits in seconds.
         *
         * How long to wait before resetting the rate limit counter.
         * Override via OTP_RATE_LIMIT_HIT_DECAY_SECONDS environment variable.
         *
         * @var int
         */
        'rate_limit_hit_decay_seconds' => env('OTP_RATE_LIMIT_HIT_DECAY_SECONDS', 60),

    ],

];
