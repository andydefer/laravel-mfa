<?php

declare(strict_types=1);

namespace Kani\Otp;

use Illuminate\Support\ServiceProvider;
use Kani\Otp\Commands\CleanupOtpsCommand;
use Kani\Otp\Commands\InstallOtpCommand;
use Kani\Otp\Services\OtpInstallerService;
use Kani\Otp\Services\OtpService;

/**
 * Laravel service provider for the OTP package.
 *
 * Handles package registration, configuration merging, command registration,
 * and service container bindings. This provider is automatically discovered
 * by Laravel when the package is installed.
 */
class OtpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * Registers console commands and publishes configuration
     * and migration files when the application is running in console mode.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerConsoleCommands();
            $this->registerPublishableAssets();
        }
    }

    /**
     * Register any package services in the container.
     *
     * Merges package configuration and binds service classes
     * as singletons for dependency injection.
     */
    public function register(): void
    {
        $this->mergePackageConfiguration();
        $this->bindOtpService();
        $this->bindOtpInstallerService();
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
     * Register the publishable assets (config and migrations).
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
     * Bind the OtpService as a singleton in the container.
     */
    private function bindOtpService(): void
    {
        $this->app->singleton(
            abstract: OtpService::class,
            concrete: function ($app): OtpService {
                return new OtpService(
                    defaultExpiryMinutes: config('otp.default_expiry_minutes', 10),
                    defaultMaxAttempts: config('otp.default_max_attempts', 3),
                    rateLimitRequests: config('otp.security.rate_limit_requests', 3),
                    rateLimitVerifications: config('otp.security.rate_limit_verifications', 5),
                    rateLimitDecayMinutes: config('otp.security.rate_limit_decay_minutes', 60)
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
