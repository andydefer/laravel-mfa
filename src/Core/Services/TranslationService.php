<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Core\Services;

use AndyDefer\Mfa\Collections\SupportedLocaleCollection;
use AndyDefer\Mfa\Contracts\Configs\MfaConfigInterface;
use AndyDefer\Mfa\Enums\SupportedLocale;
use Illuminate\Contracts\Translation\Translator;

/**
 * Service for package translations without affecting application locale.
 *
 * This service provides translation methods that use the package's
 * configured locale instead of the global application locale.
 * It ensures consistent translations regardless of the parent application's
 * current locale settings.
 */
class TranslationService
{
    public function __construct(
        private readonly Translator $translator,
        private readonly MfaConfigInterface $config,
    ) {}

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
     */
    public function trans(string $key, array $replace = []): string
    {
        $locale = $this->resolvePackageLocale();

        return $this->translator->get("mfa::{$key}", $replace, $locale);
    }

    /**
     * Retrieves a translated message using the package's configured locale.
     *
     * Alias of trans() for better readability.
     *
     * @param  string  $key  Translation key in format 'file.key'
     * @param  array<string, mixed>  $replace  Placeholder values for translation interpolation
     * @return string Translated message with placeholders replaced
     */
    public function get(string $key, array $replace = []): string
    {
        return $this->trans($key, $replace);
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param  string  $key  Translation key
     * @param  int|array  $number  Number to use for pluralization
     * @param  array<string, mixed>  $replace  Placeholder values
     * @return string Translated message with pluralization applied
     */
    public function choice(string $key, int|array $number, array $replace = []): string
    {
        $locale = $this->resolvePackageLocale();

        return $this->translator->choice("mfa::{$key}", $number, $replace, $locale);
    }

    /**
     * Retrieves the current package locale.
     *
     * @return SupportedLocale The current locale for package translations
     */
    public function getCurrentLocale(): SupportedLocale
    {
        $configuredLocale = $this->getConfiguredLocale();

        if ($this->isLocaleSupported($configuredLocale)) {
            return $configuredLocale;
        }

        return $this->getFallbackLocale();
    }

    /**
     * Retrieves the list of supported locales.
     *
     * @return SupportedLocaleCollection Collection of supported locales
     */
    public function getSupportedLocales(): SupportedLocaleCollection
    {
        $localizationConfig = $this->config->otpLocalizationConfig();

        return $localizationConfig->supported_locales;
    }

    /**
     * Resolves the current locale for package translations.
     *
     * This method follows a fallback chain defined in the configuration:
     * 1. Configured package locale (validated against supported locales)
     * 2. Fallback locale from configuration
     *
     * @return string Validated locale code to use for translations
     */
    private function resolvePackageLocale(): string
    {
        $configuredLocale = $this->getConfiguredLocale();

        if ($this->isLocaleSupported($configuredLocale)) {
            return $configuredLocale->value;
        }

        return $this->getFallbackLocale()->value;
    }

    /**
     * Retrieves the configured package locale from the config.
     *
     * @return SupportedLocale Configured locale
     */
    private function getConfiguredLocale(): SupportedLocale
    {
        $localizationConfig = $this->config->otpLocalizationConfig();
        $locale = $localizationConfig->locale;

        $supportedLocale = SupportedLocale::fromString($locale);

        return $supportedLocale ?? SupportedLocale::ENGLISH;
    }

    /**
     * Retrieves the configured fallback locale from the config.
     *
     * @return SupportedLocale Fallback locale
     */
    private function getFallbackLocale(): SupportedLocale
    {
        $localizationConfig = $this->config->otpLocalizationConfig();

        return $localizationConfig->fallback_locale;
    }

    /**
     * Validates whether a given locale is in the list of supported locales.
     *
     * @param  SupportedLocale  $locale  Locale to validate
     * @return bool True if locale is supported, false otherwise
     */
    private function isLocaleSupported(SupportedLocale $locale): bool
    {
        $supportedLocales = $this->getSupportedLocales();

        return $supportedLocales->contains($locale);
    }
}
