<?php

// src/MfaServiceProvider.php

declare(strict_types=1);

namespace AndyDefer\Mfa;

use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\FileSystemService;
use AndyDefer\Mfa\Configs\MfaConfig;
use AndyDefer\Mfa\Contracts\Configs\MfaConfigInterface;
use AndyDefer\Mfa\Core\Services\TranslationService;
use AndyDefer\Mfa\Directives\CleanupMfaDirective;
use AndyDefer\Mfa\Directives\InstallMfaDirective;
use AndyDefer\Mfa\Otp\Contracts\CodeGeneratorInterface;
use AndyDefer\Mfa\Otp\Contracts\RateLimiterInterface;
use AndyDefer\Mfa\Otp\Notifications\OtpNotification;
use AndyDefer\Mfa\Otp\Services\DefaultCodeGenerator;
use AndyDefer\Mfa\Otp\Services\LaravelRateLimiter;
use AndyDefer\Mfa\Otp\Services\OtpService;
use AndyDefer\Mfa\Totp\Services\TOTPService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

final class MfaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslations();

        if ($this->app->runningInConsole()) {
            $this->registerPublishableAssets();
        }
    }

    public function register(): void
    {
        $this->app->singleton(MfaConfigInterface::class, MfaConfig::class);

        $this->mergePackageConfiguration();
        $this->bindContracts();
        $this->bindServices();
        $this->registerDirectives();
    }

    private function registerDirectives(): void
    {
        // InstallMfaDirective
        $this->app->singleton(InstallMfaDirective::class, function (Application $app): InstallMfaDirective {
            return new InstallMfaDirective(
                context: $app->make(DirectiveContext::class),
                interaction: $app->make(DirectiveInteractionService::class),
                kernel: $app->make(Kernel::class),
                app: $app,
                filesystem: $app->make(FileSystemService::class),
                db: $app->make(DatabaseManager::class),
            );
        });

        // CleanupMfaDirective
        $this->app->singleton(CleanupMfaDirective::class, function (Application $app): CleanupMfaDirective {
            return new CleanupMfaDirective(
                context: $app->make(DirectiveContext::class),
                interaction: $app->make(DirectiveInteractionService::class),
            );
        });
    }

    private function loadTranslations(): void
    {
        $this->loadTranslationsFrom(
            path: __DIR__.'/Core/Lang',
            namespace: 'mfa'
        );
    }

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

        $this->publishes(
            paths: [__DIR__.'/Core/Lang' => $this->app->langPath('vendor/mfa')],
            groups: 'mfa-translations'
        );
    }

    private function getConfigSourcePath(): string
    {
        return __DIR__.'/../config/mfa.php';
    }

    private function getConfigDestinationPath(): string
    {
        return config_path('mfa.php');
    }

    private function getMigrationsSourcePath(): string
    {
        return __DIR__.'/../database/migrations/';
    }

    private function getMigrationsDestinationPath(): string
    {
        return database_path('migrations');
    }

    private function mergePackageConfiguration(): void
    {
        $this->mergeConfigFrom(
            path: $this->getConfigSourcePath(),
            key: 'mfa'
        );
    }

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

    private function bindServices(): void
    {
        $this->app->bind(OtpNotification::class, function (Application $app, array $parameters): OtpNotification {
            return new OtpNotification(
                otp: $parameters['otp'],
                plainCode: $parameters['plainCode'],
                translator: $app->make(TranslationService::class),
            );
        });
        $this->bindOtpService();
        $this->bindTotpService();
        $this->bindTranslationService();
    }

    private function bindOtpService(): void
    {
        $this->app->singleton(OtpService::class, function ($app): OtpService {
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
        });
    }

    private function bindTotpService(): void
    {
        $this->app->singleton(TOTPService::class, function ($app): TOTPService {
            return new TOTPService(
                period: config('mfa.totp.period', 30),
                digits: config('mfa.totp.digits', 6),
                algorithm: config('mfa.totp.algorithm', 'sha1'),
                window: config('mfa.totp.window', 1)
            );
        });
    }

    private function bindTranslationService(): void
    {
        $this->app->singleton(TranslationService::class, function (Application $app): TranslationService {
            return new TranslationService(
                translator: $app->make(Translator::class),
                config: $app->make(MfaConfigInterface::class),
            );
        });
    }
}
