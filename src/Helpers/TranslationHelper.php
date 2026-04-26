<?php

declare(strict_types=1);

namespace Kani\Otp\Helpers;

use Illuminate\Support\Facades\Lang;

/**
 * Helper for package translations without affecting application locale.
 *
 * This class provides translation methods that use the package's
 * configured locale instead of the global application locale.
 */
final class TranslationHelper
{
    /**
     * Get a translated message using the package's configured locale.
     *
     * @param string $key Translation key (e.g., 'messages.subject')
     * @param array<string, mixed> $replace Placeholders for translation
     * @return string Translated message
     */
    public static function trans(string $key, array $replace = []): string
    {
        $locale = self::getLocale();

        return Lang::get("otp::{$key}", $replace, $locale);
    }

    /**
     * Get the configured locale for the package.
     *
     * @return string Locale code (en, fr, etc.)
     */
    private static function getLocale(): string
    {
        $locale = config('otp.localization.locale', 'en');
        $supportedLocales = config('otp.localization.supported_locales', ['fr', 'en']);
        $fallbackLocale = config('otp.localization.fallback_locale', 'en');

        // Ensure values are strings (handle null values from config)
        $locale = is_string($locale) ? $locale : 'en';
        $fallbackLocale = is_string($fallbackLocale) ? $fallbackLocale : 'en';

        if (!is_array($supportedLocales)) {
            $supportedLocales = ['fr', 'en'];
        }

        if (!in_array($locale, $supportedLocales, true)) {
            return $fallbackLocale;
        }

        return $locale;
    }
}
