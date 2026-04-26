<?php

declare(strict_types=1);

namespace Kani\Mfa\Tests\Unit\Helpers;

use Kani\Mfa\Core\Helpers\TranslationHelper;
use Kani\Mfa\Tests\TestCase;

/**
 * Test suite for TranslationHelper class.
 *
 * Validates that translations are correctly loaded using the package's
 * configured locale without affecting the global application locale.
 */
final class TranslationHelperTest extends TestCase
{
    /**
     * Test that translation returns English text when locale is set to English.
     */
    public function test_trans_returns_english_text_when_locale_is_english(): void
    {
        // Arrange: Set English locale in package configuration (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'en');
        config()->set('mfa.otp.localization.fallback_locale', 'en');
        config()->set('mfa.otp.localization.supported_locales', ['fr', 'en']);

        // Act: Get a translated message
        $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test App']);
        $greeting = TranslationHelper::trans('messages.greeting');
        $defaultUserName = TranslationHelper::trans('messages.default_user_name');

        // Assert: English translations are returned
        $this->assertEquals('Your verification code - Test App', $subject);
        $this->assertEquals('Hello %s!', $greeting);
        $this->assertEquals('User', $defaultUserName);
    }

    /**
     * Test that translation returns French text when locale is set to French.
     */
    public function test_trans_returns_french_text_when_locale_is_french(): void
    {
        // Arrange: Set French locale in package configuration (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'fr');
        config()->set('mfa.otp.localization.fallback_locale', 'en');
        config()->set('mfa.otp.localization.supported_locales', ['fr', 'en']);

        // Act: Get a translated message
        $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test App']);
        $greeting = TranslationHelper::trans('messages.greeting');
        $defaultUserName = TranslationHelper::trans('messages.default_user_name');

        // Assert: French translations are returned
        $this->assertEquals('Votre code de vérification - Test App', $subject);
        $this->assertEquals('Bonjour %s !', $greeting);
        $this->assertEquals('Utilisateur', $defaultUserName);
    }

    /**
     * Test that translation falls back to fallback locale when configured locale is not supported.
     */
    public function test_trans_falls_back_to_fallback_locale_when_locale_not_supported(): void
    {
        // Arrange: Set unsupported German locale with English fallback (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'de');
        config()->set('mfa.otp.localization.supported_locales', ['fr', 'en']);
        config()->set('mfa.otp.localization.fallback_locale', 'en');

        // Act: Get a translated message
        $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test App']);

        // Assert: Falls back to English translation
        $this->assertEquals('Your verification code - Test App', $subject);
    }

    /**
     * Test that translation uses default English when no configuration is set.
     */
    public function test_trans_uses_default_english_when_no_configuration_set(): void
    {
        // Arrange: Remove any locale configuration (use defaults)
        config()->set('mfa.otp.localization.locale', null);
        config()->set('mfa.otp.localization.fallback_locale', null);
        config()->set('mfa.otp.localization.supported_locales', null);

        // Act: Get a translated message
        $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test App']);

        // Assert: Default English translation is used
        $this->assertEquals('Your verification code - Test App', $subject);
    }

    /**
     * Test that translation replaces placeholders correctly.
     */
    public function test_trans_replaces_placeholders_correctly(): void
    {
        // Arrange: Set English locale (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'en');

        // Act: Get messages with placeholders
        $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'MyAwesomeApp']);
        $expiresIn = TranslationHelper::trans('messages.expires_in', ['minutes' => 5]);
        $cancelSuccess = TranslationHelper::trans('messages.cancel_success', ['count' => 3]);

        // Assert: Placeholders are replaced with provided values
        $this->assertEquals('Your verification code - MyAwesomeApp', $subject);
        $this->assertEquals('This code will expire in 5 minute(s).', $expiresIn);
        $this->assertEquals('3 OTP(s) cancelled successfully.', $cancelSuccess);
    }

    /**
     * Test that translation handles multiple placeholders using existing translation keys.
     */
    public function test_trans_handles_multiple_placeholders_using_existing_key(): void
    {
        // Arrange: Set English locale (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'en');

        // Act: Use an existing key that contains multiple placeholders
        $message = TranslationHelper::trans('messages.invalid_code_attempts_remaining', [
            'attempts' => 2,
        ]);

        // Assert: The placeholder is replaced correctly
        $this->assertEquals('Invalid OTP code. You have 2 attempts remaining.', $message);
    }

    /**
     * Test that translation returns the key when translation does not exist.
     */
    public function test_trans_returns_key_when_translation_does_not_exist(): void
    {
        // Arrange: Set English locale (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'en');

        // Act: Request a non-existent translation key
        $result = TranslationHelper::trans('messages.non_existent_key');

        // Assert: Returns the key itself (Laravel default behavior)
        $this->assertEquals('mfa::messages.non_existent_key', $result);
    }

    /**
     * Test that translation does not affect global application locale.
     */
    public function test_trans_does_not_affect_global_application_locale(): void
    {
        // Arrange: Set application locale to French and package locale to English (nouvelle structure)
        app()->setLocale('fr');
        config()->set('mfa.otp.localization.locale', 'en');
        config()->set('mfa.otp.localization.fallback_locale', 'en');

        // Act: Get a translation from the helper
        $translatedMessage = TranslationHelper::trans('messages.subject', ['app_name' => 'Test']);

        // Assert: Package uses English but application locale remains French
        $this->assertEquals('Your verification code - Test', $translatedMessage);
        $this->assertEquals('fr', app()->getLocale());
    }

    /**
     * Test that translation respects supported locales configuration.
     */
    public function test_trans_respects_supported_locales_configuration(): void
    {
        // Arrange: Configure only French as supported (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'en');
        config()->set('mfa.otp.localization.supported_locales', ['fr']);
        config()->set('mfa.otp.localization.fallback_locale', 'fr');

        // Act: Attempt to get English translation (not supported)
        $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test']);

        // Assert: Falls back to French instead
        $this->assertEquals('Votre code de vérification - Test', $subject);
    }

    /**
     * Test that translation handles empty replace array.
     */
    public function test_trans_handles_empty_replace_array(): void
    {
        // Arrange: Set English locale (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'en');

        // Act: Get message without any replacements
        $ignoreMessage = TranslationHelper::trans('messages.ignore_request');

        // Assert: Returns the message as-is without errors
        $this->assertEquals(
            'If you did not request this verification, please ignore this email.',
            $ignoreMessage
        );
    }

    /**
     * Test that translation handles null configuration values gracefully.
     */
    public function test_trans_handles_null_configuration_values_gracefully(): void
    {
        // Arrange: Set null values for all configuration options (nouvelle structure)
        config()->set('mfa.otp.localization.locale', null);
        config()->set('mfa.otp.localization.fallback_locale', null);
        config()->set('mfa.otp.localization.supported_locales', null);

        // Act: Get a translated message
        $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test']);

        // Assert: Falls back to English default without errors
        $this->assertEquals('Your verification code - Test', $subject);
    }
}
