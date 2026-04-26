<?php

declare(strict_types=1);

namespace Kani\Mfa\Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Kani\Mfa\MfaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Abstract base test case for the Laravel OTP package.
 *
 * Provides a consistent testing environment with:
 * - SQLite in-memory database for fast, isolated tests
 * - Frozen time (2024-01-01 12:00:00) for deterministic tests
 * - Package service provider registration
 * - Package-specific configuration defaults
 * - Migration loading from both package and test directories
 */
abstract class TestCase extends Orchestra
{
    /**
     * Set up the test environment before each test.
     *
     * Freezes time to a fixed moment to ensure test consistency
     * across all test cases.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTimeForDeterministicTests();
    }

    /**
     * Clean up the test environment after each test.
     *
     * Restores the normal time behavior to prevent test pollution.
     */
    protected function tearDown(): void
    {
        $this->restoreNormalTimeBehavior();
        parent::tearDown();
    }

    /**
     * Get the package service providers to register for testing.
     *
     * @param  Application  $app  The Laravel application instance
     * @return array<int, class-string> The service providers to register
     */
    protected function getPackageProviders($app): array
    {
        return [
            MfaServiceProvider::class,
        ];
    }

    /**
     * Configure the test environment before each test.
     *
     * Sets up SQLite in-memory database and package-specific
     * configuration defaults for isolated, deterministic testing.
     *
     * @param  Application  $app  The Laravel application instance
     */
    protected function getEnvironmentSetUp($app): void
    {
        $this->configureInMemoryDatabase($app);
        $this->configurePackageDefaultsForTesting($app);
    }

    /**
     * Define and run database migrations for tests.
     *
     * Loads and executes migrations from:
     * - Package's database/migrations directory
     * - Test-specific migrations directory (if exists)
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadPackageMigrations();
        $this->loadTestMigrations();
        $this->runMigrations();
    }

    /**
     * Freeze time to a fixed moment for deterministic test results.
     */
    private function freezeTimeForDeterministicTests(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));
    }

    /**
     * Restore normal time behavior after test completion.
     */
    private function restoreNormalTimeBehavior(): void
    {
        Carbon::setTestNow();
    }

    /**
     * Configure SQLite in-memory database for fast, isolated tests.
     *
     * @param  Application  $app  The Laravel application instance
     */
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

    /**
     * Configure OTP package defaults optimized for testing.
     *
     * @param  Application  $app  The Laravel application instance
     */
    private function configurePackageDefaultsForTesting(Application $app): void
    {
        $app['config']->set('otp.default_expiry_minutes', 10);
        $app['config']->set('otp.default_max_attempts', 3);
        $app['config']->set('otp.cleanup.auto_cleanup', false);
        $app['config']->set('otp.cleanup.frequency', 60);
        $app['config']->set('otp.cleanup.retention_days', 30);
        $app['config']->set('otp.security.rate_limit_requests', 3);
        $app['config']->set('otp.security.rate_limit_verifications', 5);
        $app['config']->set('otp.security.rate_limit_decay_minutes', 60);
    }

    /**
     * Load migrations from the package's database directory.
     */
    private function loadPackageMigrations(): void
    {
        $packageMigrationsPath = __DIR__.'/../database/migrations';

        if (is_dir($packageMigrationsPath)) {
            $this->loadMigrationsFrom($packageMigrationsPath);
        }
    }

    /**
     * Load test-specific migrations if they exist.
     */
    private function loadTestMigrations(): void
    {
        $testMigrationsPath = __DIR__.'/database/migrations';

        if (is_dir($testMigrationsPath)) {
            $this->loadMigrationsFrom($testMigrationsPath);
        }
    }

    /**
     * Execute all loaded migrations.
     */
    private function runMigrations(): void
    {
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--force' => true,
        ])->run();
    }
}
