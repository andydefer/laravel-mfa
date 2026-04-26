<?php

declare(strict_types=1);

namespace Kani\Otp;

use Illuminate\Support\ServiceProvider;
use Kani\Otp\Commands\CleanupOtpsCommand;
use Kani\Otp\Commands\InstallOtpCommand;
use Kani\Otp\Contracts\CodeGeneratorInterface;
use Kani\Otp\Contracts\RateLimiterInterface;
use Kani\Otp\Services\DefaultCodeGenerator;
use Kani\Otp\Services\LaravelRateLimiter;
use Kani\Otp\Services\OtpInstallerService;
use Kani\Otp\Services\OtpService;

/**
 * Laravel service provider for the OTP package.
 *
 * Handles package registration, configuration merging, command registration,
 * service container bindings, and localization loading.
 */
final class OtpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadTranslations();

        if ($this->app->runningInConsole()) {
            $this->registerConsoleCommands();
            $this->registerPublishableAssets();
        }
    }

    /**
     * Register any package services in the container.
     */
    public function register(): void
    {
        $this->mergePackageConfiguration();
        $this->bindContracts();
        $this->bindOtpService();
        $this->bindOtpInstallerService();
    }

    /**
     * Load translations from the package's Lang directory.
     */
    private function loadTranslations(): void
    {
        $this->loadTranslationsFrom(
            path: __DIR__ . '/Lang',
            namespace: 'otp'
        );
    }

    /**
     * Register the console commands provided by the package.
     */
    private function registerConsoleCommands(): void
    {
        $this->commands([
            InstallOtpCommand::class,
            CleanupOtpsCommand::class,
        ]);
    }

    /**
     * Register the publishable assets (config, migrations, translations).
     */
    private function registerPublishableAssets(): void
    {
        $this->publishes(
            paths: [
                $this->getConfigSourcePath() => $this->getConfigDestinationPath(),
            ],
            groups: 'otp-config'
        );

        $this->publishes(
            paths: [
                $this->getMigrationsSourcePath() => $this->getMigrationsDestinationPath(),
            ],
            groups: 'otp-migrations'
        );

        $this->publishes(
            paths: [
                __DIR__ . '/Lang' => $this->app->langPath('vendor/otp'),
            ],
            groups: 'otp-translations'
        );
    }

    /**
     * Get the source path for the configuration file.
     */
    private function getConfigSourcePath(): string
    {
        return __DIR__ . '/../config/otp.php';
    }

    /**
     * Get the destination path for the configuration file.
     */
    private function getConfigDestinationPath(): string
    {
        return config_path('otp.php');
    }

    /**
     * Get the source path for the migrations directory.
     */
    private function getMigrationsSourcePath(): string
    {
        return __DIR__ . '/../database/migrations/';
    }

    /**
     * Get the destination path for the migrations directory.
     */
    private function getMigrationsDestinationPath(): string
    {
        return database_path('migrations');
    }

    /**
     * Merge the package configuration with the application's published config.
     */
    private function mergePackageConfiguration(): void
    {
        $this->mergeConfigFrom(
            path: $this->getConfigSourcePath(),
            key: 'otp'
        );
    }

    /**
     * Bind interfaces to their concrete implementations.
     */
    private function bindContracts(): void
    {
        $this->app->bind(
            abstract: CodeGeneratorInterface::class,
            concrete: DefaultCodeGenerator::class
        );

        $this->app->bind(
            abstract: RateLimiterInterface::class,
            concrete: LaravelRateLimiter::class
        );
    }

    /**
     * Bind the OtpService as a singleton in the container.
     */
    private function bindOtpService(): void
    {
        $this->app->singleton(
            abstract: OtpService::class,
            concrete: function ($app): OtpService {
                return new OtpService(
                    codeGenerator: $app->make(CodeGeneratorInterface::class),
                    rateLimiter: $app->make(RateLimiterInterface::class),
                    defaultExpiryMinutes: config('otp.default_expiry_minutes', 10),
                    defaultMaxAttempts: config('otp.default_max_attempts', 3),
                    rateLimitRequests: config('otp.security.rate_limit_requests', 3),
                    rateLimitVerifications: config('otp.security.rate_limit_verifications', 5),
                    rateLimitDecayMinutes: config('otp.security.rate_limit_decay_minutes', 60),
                    failedVerificationDecaySeconds: config('otp.security.failed_verification_decay_seconds', 300),
                    rateLimitHitDecaySeconds: config('otp.security.rate_limit_hit_decay_seconds', 60)
                );
            }
        );
    }

    /**
     * Bind the OtpInstallerService as a singleton in the container.
     */
    private function bindOtpInstallerService(): void
    {
        $this->app->singleton(
            abstract: OtpInstallerService::class,
            concrete: function ($app): OtpInstallerService {
                return new OtpInstallerService();
            }
        );
    }
}
