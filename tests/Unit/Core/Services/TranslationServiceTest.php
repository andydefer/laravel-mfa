<?php

// tests/Unit/Core/Services/TranslationServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Unit\Core\Services;

use AndyDefer\Mfa\Collections\SupportedLocaleCollection;
use AndyDefer\Mfa\Contracts\Configs\MfaConfigInterface;
use AndyDefer\Mfa\Core\Services\TranslationService;
use AndyDefer\Mfa\Enums\SupportedLocale;
use AndyDefer\Mfa\Records\OtpLocalizationConfigRecord;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class TranslationServiceTest extends TestCase
{
    private Translator&MockObject $translator;

    private MfaConfigInterface&MockObject $config;

    private TranslationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = $this->createMock(Translator::class);
        $this->config = $this->createMock(MfaConfigInterface::class);

        $this->service = new TranslationService(
            translator: $this->translator,
            config: $this->config,
        );
    }

    private function createLocalizationConfig(string $locale = 'en', array $supportedLocales = ['fr', 'en'], string $fallbackLocale = 'en'): OtpLocalizationConfigRecord
    {
        $collection = new SupportedLocaleCollection;

        foreach ($supportedLocales as $supportedLocale) {
            $localeEnum = SupportedLocale::fromString($supportedLocale);
            if ($localeEnum !== null) {
                $collection->add($localeEnum);
            }
        }

        $fallback = SupportedLocale::fromString($fallbackLocale) ?? SupportedLocale::ENGLISH;

        return new OtpLocalizationConfigRecord(
            locale: $locale,
            supported_locales: $collection,
            fallback_locale: $fallback,
        );
    }

    // ============================================================================
    // Constructor Tests
    // ============================================================================

    public function test_constructor_injects_dependencies(): void
    {
        $this->assertInstanceOf(TranslationService::class, $this->service);
    }

    // ============================================================================
    // trans() Tests
    // ============================================================================

    public function test_trans_returns_translated_string_with_default_locale(): void
    {
        $localizationConfig = $this->createLocalizationConfig('en', ['en'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('get')
            ->with('mfa::messages.welcome', [], 'en')
            ->willReturn('Welcome!');

        $result = $this->service->trans('messages.welcome');

        $this->assertSame('Welcome!', $result);
    }

    public function test_trans_returns_translated_string_with_placeholders(): void
    {
        $localizationConfig = $this->createLocalizationConfig('fr', ['fr'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('get')
            ->with('mfa::messages.welcome_user', ['name' => 'John'], 'fr')
            ->willReturn('Bienvenue John!');

        $result = $this->service->trans('messages.welcome_user', ['name' => 'John']);

        $this->assertSame('Bienvenue John!', $result);
    }

    public function test_trans_falls_back_to_fallback_locale_when_configured_locale_not_supported(): void
    {
        // ✅ Correction : 'fr' est dans supported_locales, donc 'en' n'est pas supporté
        $localizationConfig = $this->createLocalizationConfig('de', ['fr'], 'fr');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('get')
            ->with('mfa::messages.welcome', [], 'fr')
            ->willReturn('Bienvenue!');

        $result = $this->service->trans('messages.welcome');

        $this->assertSame('Bienvenue!', $result);
    }

    public function test_trans_uses_english_when_no_valid_locale_and_fallback_not_supported(): void
    {
        // ✅ Correction : 'en' est la valeur par défaut quand aucun locale n'est valide
        $localizationConfig = $this->createLocalizationConfig('de', ['fr'], 'es');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('get')
            ->with('mfa::messages.welcome', [], 'en')
            ->willReturn('Welcome!');

        $result = $this->service->trans('messages.welcome');

        $this->assertSame('Welcome!', $result);
    }

    // ============================================================================
    // get() Tests (alias of trans)
    // ============================================================================

    public function test_get_returns_translated_string(): void
    {
        $localizationConfig = $this->createLocalizationConfig('en', ['en'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('get')
            ->with('mfa::messages.hello', [], 'en')
            ->willReturn('Hello!');

        $result = $this->service->get('messages.hello');

        $this->assertSame('Hello!', $result);
    }

    // ============================================================================
    // choice() Tests
    // ============================================================================

    public function test_choice_returns_pluralized_translation_with_default_locale(): void
    {
        $localizationConfig = $this->createLocalizationConfig('en', ['en'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('choice')
            ->with('mfa::messages.apples', 5, [], 'en')
            ->willReturn('5 apples');

        $result = $this->service->choice('messages.apples', 5);

        $this->assertSame('5 apples', $result);
    }

    public function test_choice_returns_pluralized_translation_with_placeholders(): void
    {
        $localizationConfig = $this->createLocalizationConfig('fr', ['fr'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('choice')
            ->with('mfa::messages.items', 3, ['count' => 3], 'fr')
            ->willReturn('3 articles');

        $result = $this->service->choice('messages.items', 3, ['count' => 3]);

        $this->assertSame('3 articles', $result);
    }

    public function test_choice_falls_back_to_fallback_locale(): void
    {
        // ✅ Correction : 'fr' est dans supported_locales
        $localizationConfig = $this->createLocalizationConfig('de', ['fr'], 'fr');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('choice')
            ->with('mfa::messages.apples', 2, [], 'fr')
            ->willReturn('2 pommes');

        $result = $this->service->choice('messages.apples', 2);

        $this->assertSame('2 pommes', $result);
    }

    // ============================================================================
    // getCurrentLocale() Tests
    // ============================================================================

    public function test_get_current_locale_returns_configured_locale_when_supported(): void
    {
        $localizationConfig = $this->createLocalizationConfig('fr', ['fr'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $result = $this->service->getCurrentLocale();

        $this->assertSame(SupportedLocale::FRENCH, $result);
    }

    public function test_get_current_locale_returns_fallback_when_configured_not_supported(): void
    {
        // ✅ Correction : 'de' n'est pas supporté, donc retourne 'fr' (fallback)
        $localizationConfig = $this->createLocalizationConfig('de', ['fr'], 'fr');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $result = $this->service->getCurrentLocale();

        $this->assertSame(SupportedLocale::FRENCH, $result);
    }

    // ============================================================================
    // getSupportedLocales() Tests
    // ============================================================================

    public function test_get_supported_locales_returns_collection_of_supported_locales(): void
    {
        // ✅ Correction : 'es' n'est pas une valeur valide de SupportedLocale
        $localizationConfig = $this->createLocalizationConfig('en', ['fr', 'en'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $result = $this->service->getSupportedLocales();

        $this->assertInstanceOf(SupportedLocaleCollection::class, $result);
        $this->assertTrue($result->contains(SupportedLocale::FRENCH));
        $this->assertTrue($result->contains(SupportedLocale::ENGLISH));
        $this->assertCount(2, $result);
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_trans_handles_empty_replace_array(): void
    {
        $localizationConfig = $this->createLocalizationConfig('en', ['en'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('get')
            ->with('mfa::messages.empty', [], 'en')
            ->willReturn('Empty message');

        $result = $this->service->trans('messages.empty', []);

        $this->assertSame('Empty message', $result);
    }

    public function test_trans_handles_special_characters_in_replace(): void
    {
        $localizationConfig = $this->createLocalizationConfig('en', ['en'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('get')
            ->with('mfa::messages.special', ['value' => '&<>"\'\\'], 'en')
            ->willReturn('Special chars handled');

        $result = $this->service->trans('messages.special', ['value' => '&<>"\'\\']);

        $this->assertSame('Special chars handled', $result);
    }

    public function test_choice_handles_array_number(): void
    {
        $localizationConfig = $this->createLocalizationConfig('en', ['en'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->once())
            ->method('choice')
            ->with('mfa::messages.items', ['one', 'two'], [], 'en')
            ->willReturn('Selected items');

        $result = $this->service->choice('messages.items', ['one', 'two']);

        $this->assertSame('Selected items', $result);
    }

    // ============================================================================
    // Multiple Calls Tests
    // ============================================================================

    public function test_multiple_trans_calls_work_correctly(): void
    {
        $localizationConfig = $this->createLocalizationConfig('en', ['en'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['mfa::messages.welcome', [], 'en', 'Welcome!'],
                ['mfa::messages.goodbye', [], 'en', 'Goodbye!'],
            ]);

        $welcome = $this->service->trans('messages.welcome');
        $goodbye = $this->service->trans('messages.goodbye');

        $this->assertSame('Welcome!', $welcome);
        $this->assertSame('Goodbye!', $goodbye);
    }

    public function test_different_locales_in_same_test(): void
    {
        $localizationConfig = $this->createLocalizationConfig('fr', ['fr'], 'en');
        $this->config->method('otpLocalizationConfig')->willReturn($localizationConfig);

        $this->translator->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['mfa::messages.welcome', [], 'fr', 'Bienvenue!'],
                ['mfa::messages.goodbye', [], 'fr', 'Au revoir!'],
            ]);

        $welcome = $this->service->trans('messages.welcome');
        $goodbye = $this->service->trans('messages.goodbye');

        $this->assertSame('Bienvenue!', $welcome);
        $this->assertSame('Au revoir!', $goodbye);
    }
}
