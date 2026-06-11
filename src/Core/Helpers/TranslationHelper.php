<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Core\Helpers;

use Illuminate\Support\Facades\Lang;

/**
 * Helper for package translations without affecting application locale.
 *
 * This class provides translation methods that use the package's
 * configured locale instead of the global application locale.
 * It ensures consistent translations regardless of the parent application's
 * current locale settings.
 */
final class TranslationHelper
{
    /**
     * Retrieves a translated message using the package's configured locale.
     *
     * This method bypasses the global Laravel locale system to provide
     * package-specific translations independently of the main application's
     * language settings.
     *
     * @param  string  $key  Translation key in format 'file.key' (e.g., 'messages.subject')
     * @param  array<string, mixed>  $replace  Placeholder values for translation interpolation
     * @return string Translated message with placeholders replaced
     *
     * @example
     * // Returns "Two-factor authentication code"
     * TranslationHelper::trans('messages.2fa_code');
     * @example
     * // Returns "Please enter your verification code"
     * TranslationHelper::trans('messages.enter_code', ['code' => '123456']);
     */
    public static function trans(string $key, array $replace = []): string
    {
        $locale = self::resolvePackageLocale();

        return Lang::get("mfa::{$key}", $replace, $locale);
    }

    /**
     * Resolves the current locale for package translations.
     *
     * This method follows a fallback chain defined in the configuration:
     * 1. Configured package locale (validated against supported locales)
     * 2. Fallback locale from configuration
     * 3. Hardcoded 'en' as ultimate fallback (configuration should always provide this)
     *
     * @return string Validated locale code to use for translations
     */
    private static function resolvePackageLocale(): string
    {
        $configuredLocale = self::getConfiguredLocale();
        $supportedLocales = self::getSupportedLocales();
        $fallbackLocale = self::getFallbackLocale();

        if (self::isLocaleSupported($configuredLocale, $supportedLocales)) {
            return $configuredLocale;
        }

        return $fallbackLocale;
    }

    /**
     * Retrieves the configured package locale from the application config.
     *
     * @return string Configured locale code, or 'en' if not properly configured
     */
    private static function getConfiguredLocale(): string
    {
        $locale = config('mfa.otp.localization.locale', 'en');

        return is_string($locale) ? $locale : 'en';
    }

    /**
     * Retrieves the configured fallback locale from the application config.
     *
     * @return string Fallback locale code, or 'en' if not properly configured
     */
    private static function getFallbackLocale(): string
    {
        $fallbackLocale = config('mfa.otp.localization.fallback_locale', 'en');

        return is_string($fallbackLocale) ? $fallbackLocale : 'en';
    }

    /**
     * Retrieves the list of supported locales from the application config.
     *
     * @return array<int, string> List of supported locale codes
     */
    private static function getSupportedLocales(): array
    {
        $supportedLocales = config('mfa.otp.localization.supported_locales', ['fr', 'en']);

        return is_array($supportedLocales) ? $supportedLocales : ['fr', 'en'];
    }

    /**
     * Validates whether a given locale is in the list of supported locales.
     *
     * @param  string  $locale  Locale code to validate
     * @param  array<int, string>  $supportedLocales  List of allowed locale codes
     * @return bool True if locale is supported, false otherwise
     */
    private static function isLocaleSupported(string $locale, array $supportedLocales): bool
    {
        return in_array($locale, $supportedLocales, true);
    }
}
