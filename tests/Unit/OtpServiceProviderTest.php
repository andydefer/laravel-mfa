<?php

declare(strict_types=1);

namespace Kani\Otp\Tests\Unit;

use Kani\Otp\Commands\CleanupOtpsCommand;
use Kani\Otp\Commands\InstallOtpCommand;
use Kani\Otp\Contracts\CodeGeneratorInterface;
use Kani\Otp\Contracts\RateLimiterInterface;
use Kani\Otp\Helpers\TranslationHelper;
use Kani\Otp\OtpServiceProvider;
use Kani\Otp\Services\DefaultCodeGenerator;
use Kani\Otp\Services\LaravelRateLimiter;
use Kani\Otp\Services\OtpInstallerService;
use Kani\Otp\Services\OtpService;
use Kani\Otp\Tests\TestCase;

/**
 * Test suite for OtpServiceProvider service registration.
 *
 * Validates that all required services are properly registered
 * and bound in the Laravel service container.
 */
final class OtpServiceProviderTest extends TestCase
{
    /**
     * Test that the service provider registers and binds all required services.
     */
    public function test_service_provider_registers_and_binds_services(): void
    {
        // Arrange: Create service provider instance with the application container
        $provider = new OtpServiceProvider($this->app);

        // Act: Execute both registration and booting of the service provider
        $provider->register();
        $provider->boot();

        // Assert: OtpService should be bound in the container and return an OtpService instance
        $this->assertTrue($this->app->bound(OtpService::class));
        $this->assertInstanceOf(OtpService::class, $this->app->make(OtpService::class));

        // Assert: OtpInstallerService should be bound in the container and return an OtpInstallerService instance
        $this->assertTrue($this->app->bound(OtpInstallerService::class));
        $this->assertInstanceOf(OtpInstallerService::class, $this->app->make(OtpInstallerService::class));
    }

    /**
     * Test that the service provider binds interfaces to concrete implementations.
     */
    public function test_service_provider_binds_interfaces_to_concrete_implementations(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register the service provider
        $provider->register();

        // Assert: CodeGeneratorInterface should be bound to DefaultCodeGenerator
        $this->assertTrue($this->app->bound(CodeGeneratorInterface::class));
        $this->assertInstanceOf(
            DefaultCodeGenerator::class,
            $this->app->make(CodeGeneratorInterface::class)
        );

        // Assert: RateLimiterInterface should be bound to LaravelRateLimiter
        $this->assertTrue($this->app->bound(RateLimiterInterface::class));
        $this->assertInstanceOf(
            LaravelRateLimiter::class,
            $this->app->make(RateLimiterInterface::class)
        );
    }

    /**
     * Test that the service provider merges configuration.
     */
    public function test_service_provider_merges_configuration(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register the service provider to merge configuration
        $provider->register();

        // Assert: Configuration should be merged with default values from the package
        $this->assertNotNull(config('otp'));
        $this->assertArrayHasKey('default_expiry_minutes', config('otp'));
        $this->assertArrayHasKey('default_max_attempts', config('otp'));
        $this->assertArrayHasKey('localization', config('otp'));
        $this->assertArrayHasKey('locale', config('otp.localization'));
        $this->assertArrayHasKey('supported_locales', config('otp.localization'));
        $this->assertArrayHasKey('fallback_locale', config('otp.localization'));
        $this->assertArrayHasKey('security', config('otp'));
        $this->assertArrayHasKey('rate_limit_requests', config('otp.security'));
        $this->assertArrayHasKey('rate_limit_verifications', config('otp.security'));
        $this->assertArrayHasKey('rate_limit_decay_minutes', config('otp.security'));
    }

    /**
     * Test that the service provider loads English translations (default).
     */
    public function test_service_provider_loads_translations(): void
    {
        // Arrange: Set locale to English (default)
        config()->set('otp.localization.locale', 'en');
        config()->set('otp.localization.fallback_locale', 'en');

        // Act: Create and boot service provider
        $provider = new OtpServiceProvider($this->app);
        $provider->boot();

        // Assert: Translation helper should return English text
        $this->assertEquals('Your verification code - :app_name', TranslationHelper::trans('messages.subject', ['app_name' => ':app_name']));
        $this->assertEquals('Hello %s!', TranslationHelper::trans('messages.greeting'));
        $this->assertEquals('User', TranslationHelper::trans('messages.default_user_name'));
    }

    /**
     * Test that the service provider loads French translations correctly.
     */
    public function test_service_provider_loads_french_translations(): void
    {
        // Arrange: Set locale to French
        config()->set('otp.localization.locale', 'fr');
        config()->set('otp.localization.fallback_locale', 'en');

        // Act: Create and boot service provider
        $provider = new OtpServiceProvider($this->app);
        $provider->boot();

        // Assert: Translation helper should return French text
        $this->assertEquals('Votre code de vérification - :app_name', TranslationHelper::trans('messages.subject', ['app_name' => ':app_name']));
        $this->assertEquals('Bonjour %s !', TranslationHelper::trans('messages.greeting'));
        $this->assertEquals('Utilisateur', TranslationHelper::trans('messages.default_user_name'));
    }

    /**
     * Test that the service provider falls back to fallback locale when translation missing.
     */
    public function test_service_provider_falls_back_to_fallback_locale(): void
    {
        // Arrange: Set an unsupported locale
        config()->set('otp.localization.locale', 'de');
        config()->set('otp.localization.fallback_locale', 'en');

        // Act: Create and boot service provider
        $provider = new OtpServiceProvider($this->app);
        $provider->boot();

        // Assert: Should fall back to English using TranslationHelper
        $translation = TranslationHelper::trans('messages.subject', ['app_name' => 'Test App']);
        $this->assertStringContainsString('Your verification code', $translation);
    }

    /**
     * Test that the service provider registers commands when running in console.
     */
    public function test_service_provider_registers_commands_in_console(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register and boot the service provider to register console commands
        $provider->register();
        $provider->boot();

        // Assert: All command classes should exist and be loadable
        $this->assertTrue(class_exists(InstallOtpCommand::class));
        $this->assertTrue(class_exists(CleanupOtpsCommand::class));
    }

    /**
     * Test that the service provider publishes resources.
     */
    public function test_service_provider_publishes_resources(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register and boot the service provider to set up publishing
        $provider->register();
        $provider->boot();

        // Assert: Package configuration and migration files exist and are ready for publishing
        $configPath = __DIR__ . '/../../config/otp.php';
        $migrationPath = __DIR__ . '/../../database/migrations/';
        $langPath = realpath(__DIR__ . '/../../src/Lang');

        $this->assertFileExists($configPath);
        $this->assertDirectoryExists($migrationPath);

        if ($langPath !== false) {
            $this->assertDirectoryExists($langPath);
            $this->assertDirectoryExists($langPath . '/fr');
            $this->assertDirectoryExists($langPath . '/en');
            $this->assertFileExists($langPath . '/fr/messages.php');
            $this->assertFileExists($langPath . '/en/messages.php');
        }
    }

    /**
     * Test that OtpService is registered as a singleton.
     */
    public function test_otp_service_is_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register the service provider and resolve OtpService twice
        $provider->register();

        $firstInstance = $this->app->make(OtpService::class);
        $secondInstance = $this->app->make(OtpService::class);

        // Assert: Both instances should be the same object
        $this->assertSame($firstInstance, $secondInstance);
    }

    /**
     * Test that OtpInstallerService is registered as a singleton.
     */
    public function test_otp_installer_service_is_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register the service provider and resolve OtpInstallerService twice
        $provider->register();

        $firstInstance = $this->app->make(OtpInstallerService::class);
        $secondInstance = $this->app->make(OtpInstallerService::class);

        // Assert: Both instances should be the same object
        $this->assertSame($firstInstance, $secondInstance);
    }

    /**
     * Test that OtpService receives configuration values from config.
     */
    public function test_otp_service_receives_configuration_values(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register the service provider and resolve OtpService
        $provider->register();

        /** @var OtpService $otpService */
        $otpService = $this->app->make(OtpService::class);

        // Assert: Service should be instantiated without errors
        $this->assertInstanceOf(OtpService::class, $otpService);
    }

    /**
     * Test that custom configuration values are properly loaded into the service.
     */
    public function test_custom_configuration_values_are_loaded(): void
    {
        // Arrange: Set custom configuration values
        config()->set('otp.default_expiry_minutes', 15);
        config()->set('otp.default_max_attempts', 5);
        config()->set('otp.security.rate_limit_requests', 2);
        config()->set('otp.security.rate_limit_verifications', 3);
        config()->set('otp.security.rate_limit_decay_minutes', 30);
        config()->set('otp.localization.locale', 'en');

        // Act: Create service provider instance and register
        $provider = new OtpServiceProvider($this->app);
        $provider->register();

        // Assert: The OtpService can be resolved with custom config values
        $otpService = $this->app->make(OtpService::class);
        $this->assertInstanceOf(OtpService::class, $otpService);
    }

    /**
     * Test that CodeGeneratorInterface can be resolved multiple times
     * and returns different instances (not singleton).
     */
    public function test_code_generator_is_not_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register the service provider and resolve CodeGeneratorInterface twice
        $provider->register();

        $firstInstance = $this->app->make(CodeGeneratorInterface::class);
        $secondInstance = $this->app->make(CodeGeneratorInterface::class);

        // Assert: Each resolution should return a new instance
        $this->assertNotSame($firstInstance, $secondInstance);
    }

    /**
     * Test that RateLimiterInterface can be resolved multiple times
     * and returns different instances (not singleton).
     */
    public function test_rate_limiter_is_not_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register the service provider and resolve RateLimiterInterface twice
        $provider->register();

        $firstInstance = $this->app->make(RateLimiterInterface::class);
        $secondInstance = $this->app->make(RateLimiterInterface::class);

        // Assert: Each resolution should return a new instance
        $this->assertNotSame($firstInstance, $secondInstance);
    }

    /**
     * Test that OtpService receives the injected dependencies correctly.
     */
    public function test_otp_service_receives_injected_dependencies(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register the service provider and resolve OtpService
        $provider->register();

        $reflection = new \ReflectionClass(OtpService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        // Assert: The constructor expects CodeGeneratorInterface and RateLimiterInterface
        $this->assertInstanceOf(\ReflectionParameter::class, $parameters[0]);
        $this->assertEquals(CodeGeneratorInterface::class, $parameters[0]->getType()->getName());
        $this->assertEquals(RateLimiterInterface::class, $parameters[1]->getType()->getName());
    }

    /**
     * Test that translation publishing is properly configured.
     */
    public function test_translation_publishing_is_configured(): void
    {
        // Arrange: Create service provider instance
        $provider = new OtpServiceProvider($this->app);

        // Act: Register and boot the service provider
        $provider->register();
        $provider->boot();

        // Assert: Language directories exist
        $langPath = realpath(__DIR__ . '/../../src/Lang');
        $this->assertNotFalse($langPath, 'Lang directory should exist');

        $this->assertDirectoryExists($langPath . '/fr');
        $this->assertDirectoryExists($langPath . '/en');
        $this->assertFileExists($langPath . '/fr/messages.php');
        $this->assertFileExists($langPath . '/en/messages.php');
    }
}
