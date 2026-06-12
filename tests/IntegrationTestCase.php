<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests;

use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Services\FileSystemService;
use AndyDefer\Mfa\MfaServiceProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTimeForDeterministicTests();
    }

    protected function tearDown(): void
    {
        $this->restoreNormalTimeBehavior();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            DirectiveServiceProvider::class,
            MfaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $this->configureInMemoryDatabase($app);
        $this->configurePackageDefaultsForTesting($app);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadPackageMigrations();
        $this->loadTestMigrations();
        $this->runMigrations();
    }

    private function freezeTimeForDeterministicTests(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));
    }

    private function restoreNormalTimeBehavior(): void
    {
        Carbon::setTestNow();
    }

    private function configureInMemoryDatabase(Application $app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }

    private function configurePackageDefaultsForTesting(Application $app): void
    {
        // ✅ CORRECTION : Config MFA
        $app['config']->set('mfa.cleanup.retention_days', 30);
        $app['config']->set('mfa.cleanup.auto_cleanup', true);
        $app['config']->set('mfa.cleanup.frequency', 60);

        // Config OTP
        $app['config']->set('mfa.otp.default_expiry_minutes', 10);
        $app['config']->set('mfa.otp.default_max_attempts', 3);

        // Config TOTP
        $app['config']->set('mfa.totp.period', 30);
        $app['config']->set('mfa.totp.digits', 6);
        $app['config']->set('mfa.totp.algorithm', 'sha1');

        // Debug: afficher la config
    }

    private function loadPackageMigrations(): void
    {
        $packageMigrationsPath = __DIR__ . '/../database/migrations';

        if (is_dir($packageMigrationsPath)) {
            $this->loadMigrationsFrom($packageMigrationsPath);
        }
    }

    private function loadTestMigrations(): void
    {
        $testMigrationsPath = __DIR__ . '/database/migrations';
        $fileSystem = new FileSystemService;


        if (is_dir($testMigrationsPath)) {
            $this->loadMigrationsFrom($testMigrationsPath);
        }
    }

    private function runMigrations(): void
    {
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--force' => true,
        ])->run();
    }
}
