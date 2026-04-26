<?php

declare(strict_types=1);

// config/mfa.php

/**
 * MFA (Multi-Factor Authentication) Package Configuration.
 *
 * This configuration file controls all aspects of the MFA package behavior:
 * - OTP settings (expiry, attempts)
 * - TOTP settings (Google Authenticator)
 * - Recovery codes settings
 * - Automatic cleanup of expired/used OTPs and old 2FA secrets
 * - Security rate limiting to prevent abuse
 * - Localization settings for multilingual support
 *
 * All settings can be overridden via environment variables using the
 * MFA_* prefix as documented in each section.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | OTP (One-Time Password) Settings
    |--------------------------------------------------------------------------
    |
    | These values define the standard behavior for all new OTPs unless
    | explicitly overridden at creation time.
    |
    */

    'otp' => [

        /**
         * Default lifetime of an OTP in minutes.
         *
         * After this period, the OTP expires and cannot be used for verification.
         * Override via MFA_OTP_DEFAULT_EXPIRY_MINUTES environment variable.
         *
         * @var int
         */
        'default_expiry_minutes' => env('MFA_OTP_DEFAULT_EXPIRY_MINUTES', 10),

        /**
         * Default maximum number of verification attempts allowed.
         *
         * Prevents brute-force attacks by limiting how many times an OTP
         * can be tried before being automatically invalidated.
         * Override via MFA_OTP_DEFAULT_MAX_ATTEMPTS environment variable.
         *
         * @var int
         */
        'default_max_attempts' => env('MFA_OTP_DEFAULT_MAX_ATTEMPTS', 3),

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
             * Override via MFA_OTP_LOCALE environment variable.
             *
             * @var string
             */
            'locale' => env('MFA_OTP_LOCALE', 'en'),

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
            'fallback_locale' => env('MFA_OTP_FALLBACK_LOCALE', 'en'),

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
             * Override via MFA_OTP_RATE_LIMIT_REQUESTS environment variable.
             *
             * @var int
             */
            'rate_limit_requests' => env('MFA_OTP_RATE_LIMIT_REQUESTS', 3),

            /**
             * Maximum OTP verification attempts per time window.
             *
             * Limits how many failed verification attempts are allowed before
             * blocking further attempts, preventing brute-force attacks.
             * Override via MFA_OTP_RATE_LIMIT_VERIFICATIONS environment variable.
             *
             * @var int
             */
            'rate_limit_verifications' => env('MFA_OTP_RATE_LIMIT_VERIFICATIONS', 5),

            /**
             * Rate limit time window in minutes.
             *
             * The duration during which the rate limits (requests and verifications)
             * are enforced. After this period, the counters are reset.
             * Override via MFA_OTP_RATE_LIMIT_DECAY_MINUTES environment variable.
             *
             * @var int
             */
            'rate_limit_decay_minutes' => env('MFA_OTP_RATE_LIMIT_DECAY_MINUTES', 60),

            /**
             * Decay time for failed verification attempts in seconds.
             *
             * How long to block verification attempts after a failure.
             * Override via MFA_OTP_FAILED_VERIFICATION_DECAY_SECONDS environment variable.
             *
             * @var int
             */
            'failed_verification_decay_seconds' => env('MFA_OTP_FAILED_VERIFICATION_DECAY_SECONDS', 300),

            /**
             * Decay time for rate limit hits in seconds.
             *
             * How long to wait before resetting the rate limit counter.
             * Override via MFA_OTP_RATE_LIMIT_HIT_DECAY_SECONDS environment variable.
             *
             * @var int
             */
            'rate_limit_hit_decay_seconds' => env('MFA_OTP_RATE_LIMIT_HIT_DECAY_SECONDS', 60),

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | TOTP (Time-based One-Time Password) Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Authenticator compatible two-factor authentication.
    | These settings control the behavior of TOTP code generation and verification.
    |
    */

    'totp' => [

        /**
         * Time period in seconds for each TOTP code.
         *
         * Standard is 30 seconds. Determines how long a code remains valid.
         * Override via MFA_TOTP_PERIOD environment variable.
         *
         * @var int
         */
        'period' => env('MFA_TOTP_PERIOD', 30),

        /**
         * Number of digits in the TOTP code.
         *
         * Standard is 6 digits. Some applications support 8 digits.
         * Override via MFA_TOTP_DIGITS environment variable.
         *
         * @var int
         */
        'digits' => env('MFA_TOTP_DIGITS', 6),

        /**
         * Hashing algorithm for TOTP generation.
         *
         * Supported: 'sha1', 'sha256', 'sha512'
         * SHA1 is the standard and most compatible.
         * Override via MFA_TOTP_ALGORITHM environment variable.
         *
         * @var string
         */
        'algorithm' => env('MFA_TOTP_ALGORITHM', 'sha1'),

        /**
         * Application issuer name for QR codes.
         *
         * Displayed in Google Authenticator as the account issuer.
         * Defaults to your Laravel application name.
         * Override via MFA_TOTP_ISSUER environment variable.
         *
         * @var string|null
         */
        'issuer' => env('MFA_TOTP_ISSUER', config('app.name')),

        /**
         * Verification window in number of periods.
         *
         * Number of time periods on each side to check for code validity.
         * window=1 means check current period, 1 past, and 1 future.
         * Increase this value to account for clock drift.
         * Override via MFA_TOTP_WINDOW environment variable.
         *
         * @var int
         */
        'window' => env('MFA_TOTP_WINDOW', 1),

    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Codes Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for recovery codes generation used in two-factor authentication.
    | These settings control how recovery codes are generated and validated.
    |
    */

    'recovery_codes' => [

        /**
         * Characters used for recovery code generation.
         *
         * Excludes ambiguous characters: O, 0, I, 1 for better readability.
         * Override via MFA_RECOVERY_CODE_CHARS environment variable.
         *
         * @var string
         */
        'characters' => env('MFA_RECOVERY_CODE_CHARS', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'),

        /**
         * Default number of recovery codes to generate per user.
         *
         * Number of one-time use backup codes provided to the user.
         * Override via MFA_RECOVERY_CODES_COUNT environment variable.
         *
         * @var int
         */
        'default_count' => env('MFA_RECOVERY_CODES_COUNT', 8),

        /**
         * Default length of each recovery code.
         *
         * Length of each individual recovery code string (without dashes).
         * Override via MFA_RECOVERY_CODE_LENGTH environment variable.
         *
         * @var int
         */
        'default_length' => env('MFA_RECOVERY_CODE_LENGTH', 10),

        /**
         * Hashing algorithm for recovery codes storage.
         *
         * Algorithm used to hash recovery codes before storing in database.
         * Supported: 'sha256', 'sha512', 'bcrypt'
         * Override via MFA_RECOVERY_CODE_HASH_ALGO environment variable.
         *
         * @var string
         */
        'hash_algorithm' => env('MFA_RECOVERY_CODE_HASH_ALGO', 'sha256'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Automatic garbage collection for old OTP records and 2FA secrets
    | to maintain database performance and compliance with data retention policies.
    |
    */

    'cleanup' => [

        /**
         * Enable automatic cleanup of expired/used OTPs and old 2FA secrets.
         *
         * When true, old records are automatically removed from the database.
         * When false, manual cleanup is required via the cleanup command.
         * Override via MFA_CLEANUP_AUTO_CLEANUP environment variable.
         *
         * @var bool
         */
        'auto_cleanup' => env('MFA_CLEANUP_AUTO_CLEANUP', true),

        /**
         * Cleanup execution frequency in minutes.
         *
         * How often the scheduled cleanup job should run when auto_cleanup is enabled.
         * Override via MFA_CLEANUP_FREQUENCY environment variable.
         *
         * @var int
         */
        'frequency' => env('MFA_CLEANUP_FREQUENCY', 60),

        /**
         * Number of days to retain OTP and 2FA records.
         *
         * Records older than this value are considered stale and eligible for cleanup:
         * - Expired OTPs
         * - Verified/used OTPs
         * - Cancelled OTPs
         * - Disabled 2FA secrets
         * - Unused confirmed 2FA secrets
         * 
         * Override via MFA_CLEANUP_RETENTION_DAYS environment variable.
         *
         * @var int
         */
        'retention_days' => env('MFA_CLEANUP_RETENTION_DAYS', 30),

    ],

];
