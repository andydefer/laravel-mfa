<?php

// src/MfaServiceProvider.php

declare(strict_types=1);

namespace Kani\Mfa;

use Illuminate\Support\ServiceProvider;
use Kani\Mfa\Core\Commands\CleanupMfaCommand;
use Kani\Mfa\Core\Commands\InstallMfaCommand;
use Kani\Mfa\Core\Services\MfaInstallerService;
use Kani\Mfa\Otp\Contracts\CodeGeneratorInterface;
use Kani\Mfa\Otp\Contracts\RateLimiterInterface;
use Kani\Mfa\Otp\Services\DefaultCodeGenerator;
use Kani\Mfa\Otp\Services\LaravelRateLimiter;
use Kani\Mfa\Otp\Services\OtpService;
use Kani\Mfa\Totp\Services\TOTPService;

/**
 * Laravel service provider for the MFA package.
 *
 * Handles package registration, configuration merging, command registration,
 * service container bindings, and localization loading.
 */
final class MfaServiceProvider extends ServiceProvider
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
        $this->bindMfaInstallerService();
        $this->bindTotpService();
    }

    /**
     * Load translations from the package's Lang directory.
     */
    private function loadTranslations(): void
    {
        // Correction: le dossier Lang est maintenant dans Core/
        $this->loadTranslationsFrom(
            path: __DIR__.'/Core/Lang',
            namespace: 'mfa'
        );
    }

    /**
     * Register the console commands provided by the package.
     */
    private function registerConsoleCommands(): void
    {
        $this->commands([
            InstallMfaCommand::class,
            CleanupMfaCommand::class,
        ]);
    }

    /**
     * Register the publishable assets (config, migrations, translations).
     */
    private function registerPublishableAssets(): void
    {
        $this->publishes(
            paths: [$this->getConfigSourcePath() => $this->getConfigDestinationPath()],
            groups: 'mfa-config'
        );

        $this->publishes(
            paths: [$this->getMigrationsSourcePath() => $this->getMigrationsDestinationPath()],
            groups: 'mfa-migrations'
        );

        // Correction: le dossier Lang est maintenant dans Core/
        $this->publishes(
            paths: [__DIR__.'/Core/Lang' => $this->app->langPath('vendor/mfa')],
            groups: 'mfa-translations'
        );
    }

    /**
     * Get the source path for the configuration file.
     */
    private function getConfigSourcePath(): string
    {
        return __DIR__.'/../config/mfa.php';
    }

    /**
     * Get the destination path for the configuration file.
     */
    private function getConfigDestinationPath(): string
    {
        return config_path('mfa.php');
    }

    /**
     * Get the source path for the migrations directory.
     */
    private function getMigrationsSourcePath(): string
    {
        return __DIR__.'/../database/migrations/';
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
            key: 'mfa'
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
                    defaultExpiryMinutes: config('mfa.otp.default_expiry_minutes', 10),
                    defaultMaxAttempts: config('mfa.otp.default_max_attempts', 3),
                    rateLimitRequests: config('mfa.otp.security.rate_limit_requests', 3),
                    rateLimitVerifications: config('mfa.otp.security.rate_limit_verifications', 5),
                    rateLimitDecayMinutes: config('mfa.otp.security.rate_limit_decay_minutes', 60),
                    failedVerificationDecaySeconds: config('mfa.otp.security.failed_verification_decay_seconds', 300),
                    rateLimitHitDecaySeconds: config('mfa.otp.security.rate_limit_hit_decay_seconds', 60)
                );
            }
        );
    }

    /**
     * Bind the MfaInstallerService as a singleton in the container.
     */
    private function bindMfaInstallerService(): void
    {
        $this->app->singleton(
            abstract: MfaInstallerService::class,
            concrete: function ($app): MfaInstallerService {
                return new MfaInstallerService;
            }
        );
    }

    /**
     * Bind the TOTPService as a singleton in the container.
     */
    private function bindTotpService(): void
    {
        $this->app->singleton(TOTPService::class, function ($app) {
            return new TOTPService(
                period: config('mfa.totp.period', 30),
                digits: config('mfa.totp.digits', 6),
                algorithm: config('mfa.totp.algorithm', 'sha1'),
                window: config('mfa.totp.window', 1)
            );
        });
    }
}
