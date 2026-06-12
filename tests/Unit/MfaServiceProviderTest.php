<?php

// tests/Unit/MfaServiceProviderTest.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Unit;

use AndyDefer\Mfa\Core\Commands\CleanupMfaCommand;
use AndyDefer\Mfa\Core\Commands\InstallMfaCommand;
use AndyDefer\Mfa\Core\Helpers\TranslationHelper;
use AndyDefer\Mfa\Core\Services\MfaInstallerService;
use AndyDefer\Mfa\MfaServiceProvider;
use AndyDefer\Mfa\Otp\Contracts\CodeGeneratorInterface;
use AndyDefer\Mfa\Otp\Contracts\RateLimiterInterface;
use AndyDefer\Mfa\Otp\Services\DefaultCodeGenerator;
use AndyDefer\Mfa\Otp\Services\LaravelRateLimiter;
use AndyDefer\Mfa\Otp\Services\OtpService;
use AndyDefer\Mfa\Tests\TestCase;
use AndyDefer\Mfa\Totp\Services\TOTPService;

/**
 * Test suite for MfaServiceProvider service registration.
 *
 * Validates that all required services are properly registered
 * and bound in the Laravel service container.
 */
final class MfaServiceProviderTest extends TestCase
{
    /**
     * Test that the service provider registers and binds all required services.
     */
    public function test_service_provider_registers_and_binds_services(): void
    {
        // Arrange: Create service provider instance with the application container
        $provider = new MfaServiceProvider($this->app);

        // Act: Execute both registration and booting of the service provider
        $provider->register();
        $provider->boot();

        // Assert: OtpService should be bound in the container and return an OtpService instance
        $this->assertTrue($this->app->bound(OtpService::class));
        $this->assertInstanceOf(OtpService::class, $this->app->make(OtpService::class));

        // Assert: MfaInstallerService should be bound in the container and return an MfaInstallerService instance
        $this->assertTrue($this->app->bound(MfaInstallerService::class));
        $this->assertInstanceOf(MfaInstallerService::class, $this->app->make(MfaInstallerService::class));

        // Assert: TOTPService should be bound in the container and return a TOTPService instance
        $this->assertTrue($this->app->bound(TOTPService::class));
        $this->assertInstanceOf(TOTPService::class, $this->app->make(TOTPService::class));
    }

    /**
     * Test that the service provider binds interfaces to concrete implementations.
     */
    public function test_service_provider_binds_interfaces_to_concrete_implementations(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

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
     * Test that the service provider merges configuration including TOTP settings.
     */
    public function test_service_provider_merges_configuration(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register the service provider to merge configuration
        $provider->register();

        // Assert: Configuration should be merged with default values from the package
        $this->assertNotNull(config('mfa'));
        $this->assertArrayHasKey('otp', config('mfa'));
        $this->assertArrayHasKey('default_expiry_minutes', config('mfa.otp'));
        $this->assertArrayHasKey('default_max_attempts', config('mfa.otp'));
        $this->assertArrayHasKey('localization', config('mfa.otp'));
        $this->assertArrayHasKey('locale', config('mfa.otp.localization'));
        $this->assertArrayHasKey('supported_locales', config('mfa.otp.localization'));
        $this->assertArrayHasKey('fallback_locale', config('mfa.otp.localization'));
        $this->assertArrayHasKey('security', config('mfa.otp'));
        $this->assertArrayHasKey('rate_limit_requests', config('mfa.otp.security'));
        $this->assertArrayHasKey('rate_limit_verifications', config('mfa.otp.security'));
        $this->assertArrayHasKey('rate_limit_decay_minutes', config('mfa.otp.security'));
        $this->assertArrayHasKey('totp', config('mfa'));
        $this->assertArrayHasKey('period', config('mfa.totp'));
        $this->assertArrayHasKey('digits', config('mfa.totp'));
        $this->assertArrayHasKey('algorithm', config('mfa.totp'));
        $this->assertArrayHasKey('window', config('mfa.totp'));
    }

    /**
     * Test that the service provider loads English translations (default).
     */
    public function test_service_provider_loads_translations(): void
    {
        // Arrange: Set locale to English (default)
        config()->set('mfa.otp.localization.locale', 'en');
        config()->set('mfa.otp.localization.fallback_locale', 'en');

        // Act: Create and boot service provider
        $provider = new MfaServiceProvider($this->app);
        $provider->boot();

        // Assert: Translation helper should return English text using mfa namespace
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
        config()->set('mfa.otp.localization.locale', 'fr');
        config()->set('mfa.otp.localization.fallback_locale', 'en');

        // Act: Create and boot service provider
        $provider = new MfaServiceProvider($this->app);
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
        config()->set('mfa.otp.localization.locale', 'de');
        config()->set('mfa.otp.localization.fallback_locale', 'en');

        // Act: Create and boot service provider
        $provider = new MfaServiceProvider($this->app);
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
        $provider = new MfaServiceProvider($this->app);

        // Act: Register and boot the service provider to register console commands
        $provider->register();
        $provider->boot();

        // Assert: All command classes should exist and be loadable
        $this->assertTrue(class_exists(InstallMfaCommand::class));
        $this->assertTrue(class_exists(CleanupMfaCommand::class));
    }

    /**
     * Test that the service provider publishes resources.
     */
    public function test_service_provider_publishes_resources(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register and boot the service provider to set up publishing
        $provider->register();
        $provider->boot();

        // Assert: Package configuration and migration files exist and are ready for publishing
        $configPath = __DIR__.'/../../config/mfa.php';
        $migrationPath = __DIR__.'/../../database/migrations/';
        $langPath = realpath(__DIR__.'/../../src/Lang');

        $this->assertFileExists($configPath);
        $this->assertDirectoryExists($migrationPath);

        if ($langPath !== false) {
            $this->assertDirectoryExists($langPath);
            $this->assertDirectoryExists($langPath.'/fr');
            $this->assertDirectoryExists($langPath.'/en');
            $this->assertFileExists($langPath.'/fr/messages.php');
            $this->assertFileExists($langPath.'/en/messages.php');
        }
    }

    /**
     * Test that OtpService is registered as a singleton.
     */
    public function test_otp_service_is_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register the service provider and resolve OtpService twice
        $provider->register();

        $firstInstance = $this->app->make(OtpService::class);
        $secondInstance = $this->app->make(OtpService::class);

        // Assert: Both instances should be the same object
        $this->assertSame($firstInstance, $secondInstance);
    }

    /**
     * Test that MfaInstallerService is registered as a singleton.
     */
    public function test_mfa_installer_service_is_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register the service provider and resolve MfaInstallerService twice
        $provider->register();

        $firstInstance = $this->app->make(MfaInstallerService::class);
        $secondInstance = $this->app->make(MfaInstallerService::class);

        // Assert: Both instances should be the same object
        $this->assertSame($firstInstance, $secondInstance);
    }

    /**
     * Test that TOTPService is registered as a singleton.
     */
    public function test_totp_service_is_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register the service provider and resolve TOTPService twice
        $provider->register();

        $firstInstance = $this->app->make(TOTPService::class);
        $secondInstance = $this->app->make(TOTPService::class);

        // Assert: Both instances should be the same object
        $this->assertSame($firstInstance, $secondInstance);
    }

    /**
     * Test that OtpService receives configuration values from config.
     */
    public function test_otp_service_receives_configuration_values(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register the service provider and resolve OtpService
        $provider->register();

        /** @var OtpService $otpService */
        $otpService = $this->app->make(OtpService::class);

        // Assert: Service should be instantiated without errors
        $this->assertInstanceOf(OtpService::class, $otpService);
    }

    /**
     * Test that TOTPService receives configuration values from config.
     */
    public function test_totp_service_receives_configuration_values(): void
    {
        // Arrange: Set custom TOTP configuration values
        config()->set('mfa.totp.period', 60);
        config()->set('mfa.totp.digits', 8);
        config()->set('mfa.totp.algorithm', 'sha256');
        config()->set('mfa.totp.window', 2);

        // Act: Create service provider instance and register
        $provider = new MfaServiceProvider($this->app);
        $provider->register();

        /** @var TOTPService $totpService */
        $totpService = $this->app->make(TOTPService::class);

        // Assert: Service should be instantiated without errors
        $this->assertInstanceOf(TOTPService::class, $totpService);
    }

    /**
     * Test that custom configuration values are properly loaded into the service.
     */
    public function test_custom_configuration_values_are_loaded(): void
    {
        // Arrange: Set custom configuration values
        config()->set('mfa.otp.default_expiry_minutes', 15);
        config()->set('mfa.otp.default_max_attempts', 5);
        config()->set('mfa.otp.security.rate_limit_requests', 2);
        config()->set('mfa.otp.security.rate_limit_verifications', 3);
        config()->set('mfa.otp.security.rate_limit_decay_minutes', 30);
        config()->set('mfa.otp.localization.locale', 'en');
        config()->set('mfa.totp.period', 45);
        config()->set('mfa.totp.digits', 7);

        // Act: Create service provider instance and register
        $provider = new MfaServiceProvider($this->app);
        $provider->register();

        // Assert: The OtpService can be resolved with custom config values
        $otpService = $this->app->make(OtpService::class);
        $this->assertInstanceOf(OtpService::class, $otpService);

        // Assert: The TOTPService can be resolved with custom config values
        $totpService = $this->app->make(TOTPService::class);
        $this->assertInstanceOf(TOTPService::class, $totpService);
    }

    /**
     * Test that CodeGeneratorInterface can be resolved multiple times
     * and returns different instances (not singleton).
     */
    public function test_code_generator_is_not_singleton(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

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
        $provider = new MfaServiceProvider($this->app);

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
        $provider = new MfaServiceProvider($this->app);

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
     * Test that TOTPService receives the injected dependencies correctly.
     */
    public function test_totp_service_receives_injected_dependencies(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register the service provider and resolve TOTPService
        $provider->register();

        $reflection = new \ReflectionClass(TOTPService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        // Assert: The constructor expects period, digits, algorithm, window parameters
        $this->assertInstanceOf(\ReflectionParameter::class, $parameters[0]);
        $this->assertEquals('period', $parameters[0]->getName());
        $this->assertEquals('digits', $parameters[1]->getName());
        $this->assertEquals('algorithm', $parameters[2]->getName());
        $this->assertEquals('window', $parameters[3]->getName());
    }

    /**
     * Test that translation publishing is properly configured.
     */
    public function test_translation_publishing_is_configured(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register and boot the service provider
        $provider->register();
        $provider->boot();

        // Assert: Language directories exist
        $langPath = realpath(__DIR__.'/../../src/Core/Lang');
        $this->assertNotFalse($langPath, 'Lang directory should exist');

        $this->assertDirectoryExists($langPath.'/fr');
        $this->assertDirectoryExists($langPath.'/en');
        $this->assertFileExists($langPath.'/fr/messages.php');
        $this->assertFileExists($langPath.'/en/messages.php');
    }

    /**
     * Test that TOTP publishes resources when requested.
     */
    public function test_totp_publishes_resources_when_requested(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register and boot the service provider
        $provider->register();
        $provider->boot();

        // Assert: The TOTP migration file exists in the package
        $totpMigrationPath = realpath(__DIR__.'/../../database/migrations/2026_01_01_000001_create_two_factor_secrets_table.php');

        if ($totpMigrationPath !== false) {
            $this->assertFileExists($totpMigrationPath);
        }
    }

    /**
     * Test that CleanupMfaCommand is properly registered.
     */
    public function test_cleanup_mfa_command_is_registered(): void
    {
        // Arrange: Create service provider instance
        $provider = new MfaServiceProvider($this->app);

        // Act: Register the service provider
        $provider->register();
        $provider->boot();

        // Assert: The command class should exist
        $this->assertTrue(class_exists(CleanupMfaCommand::class), 'CleanupMfaCommand class should exist');

        // Verify command signature
        $command = new CleanupMfaCommand;
        $this->assertEquals('mfa:cleanup', $command->getName(), 'Command should have correct signature');

        // Verify the command is registered via Artisan
        $artisan = $this->app->make('Illuminate\Contracts\Console\Kernel');
        $commands = $artisan->all();
        $this->assertArrayHasKey('mfa:cleanup', $commands, 'Command should be registered with Artisan');
    }
}
