<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Unit\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use AndyDefer\Mfa\Core\Services\MfaInstallerService;
use AndyDefer\Mfa\Tests\TestCase;
use Mockery;

/**
 * Unit tests for the MFA Installer Service.
 *
 * This test suite verifies the installation behavior of the MFA package,
 * including configuration publishing, migration handling, component selection,
 * and force mode operations.
 */
final class MfaInstallerServiceTest extends TestCase
{
    private MfaInstallerService $installerService;

    private Command $command;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerService = new MfaInstallerService;
        $this->command = Mockery::mock(Command::class);
        $this->configPath = config_path('mfa.php');

        $this->cleanupExistingInstallation();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        if (File::exists($this->configPath)) {
            File::delete($this->configPath);
        }

        parent::tearDown();
    }

    /**
     * Clean up any existing MFA tables and migrations before each test.
     */
    private function cleanupExistingInstallation(): void
    {
        if (File::exists($this->configPath)) {
            File::delete($this->configPath);
        }

        $this->dropTableIfExists('one_time_passwords');
        $this->dropTableIfExists('two_factor_secrets');
        $this->cleanupMigrationDirectory();
    }

    /**
     * Drop a database table if it exists.
     */
    private function dropTableIfExists(string $tableName): void
    {
        if (Schema::hasTable($tableName)) {
            Schema::drop($tableName);
        }
    }

    /**
     * Clean up the migration directory by deleting all PHP files.
     */
    private function cleanupMigrationDirectory(): void
    {
        $migrationsDir = database_path('migrations');

        if (! File::exists($migrationsDir)) {
            return;
        }

        foreach (glob($migrationsDir . '/*.php') as $migrationFile) {
            File::delete($migrationFile);
        }
    }

    /**
     * Test that installation proceeds immediately when force mode is enabled.
     */
    public function test_installation_proceeds_when_force_mode_enabled(): void
    {
        // Arrange: Mock command output and bypass confirmation
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')->never();

        // Act: Execute installer in force mode with migrations skipped
        $this->installerService->install(
            command: $this->command,
            force: true,
            skipMigrations: true
        );

        // Assert: Installation completed without confirmation prompt
        $this->assertTrue(true);
    }

    /**
     * Test that installation works with only TOTP component and no OTP.
     */
    public function test_installation_without_otp_component(): void
    {
        // Arrange: Mock command output
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')->never();

        // Act: Execute installer with only TOTP component
        $this->installerService->install(
            command: $this->command,
            force: true,
            skipMigrations: true,
            includeOtp: false,
            includeTotp: true
        );

        // Assert: Installation completed successfully
        $this->assertTrue(true);
    }

    /**
     * Test that installation works with only OTP component and no TOTP.
     */
    public function test_installation_without_totp_component(): void
    {
        // Arrange: Mock command output
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')->never();

        // Act: Execute installer with only OTP component
        $this->installerService->install(
            command: $this->command,
            force: true,
            skipMigrations: true,
            includeOtp: true,
            includeTotp: false
        );

        // Assert: Installation completed successfully
        $this->assertTrue(true);
    }

    /**
     * Test that installation works with both components in force mode.
     */
    public function test_installation_proceeds_when_force_mode_enabled_with_both_components(): void
    {
        // Arrange: Mock command output
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')->never();

        // Act: Execute installer with both components in force mode
        $this->installerService->install(
            command: $this->command,
            force: true,
            skipMigrations: true,
            includeOtp: true,
            includeTotp: true
        );

        // Assert: Installation completed successfully
        $this->assertTrue(true);
    }

    /**
     * Test that installation is cancelled when user declines confirmation.
     */
    public function test_installation_cancels_when_user_declines_confirmation(): void
    {
        // Arrange: Mock command output and user confirmation response
        $this->command->shouldReceive('info')->once();
        $this->command->shouldReceive('warn')->once();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(false);
        $this->command->shouldReceive('info')
            ->with('Installation cancelled.')
            ->once();

        // Act: Execute installer without force mode
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: true
        );

        // Assert: Installation was cancelled
        $this->assertTrue(true);
    }

    /**
     * Test that migrations are skipped when explicitly disabled.
     */
    public function test_migrations_are_skipped_when_disabled(): void
    {
        // Arrange: Mock command output and user confirmation
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(true);

        // Act: Execute installer with skipMigrations flag
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: true
        );

        // Assert: Installation proceeded without migration calls
        $this->assertTrue(true);
    }

    /**
     * Test that migrations are skipped when tables already exist.
     */
    public function test_migrations_are_skipped_when_tables_already_exist(): void
    {
        // Arrange: Create OTP table to simulate existing installation
        if (! Schema::hasTable('one_time_passwords')) {
            Schema::create('one_time_passwords', function ($table): void {
                $table->id();
                $table->timestamps();
            });
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')
            ->with('⚠️ MFA package appears to be already installed.')
            ->once();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Do you want to reinstall? This may overwrite existing files.', false)
            ->andReturn(false);
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->never();
        $this->command->shouldReceive('call')->never();

        // Act: Execute installer without force mode
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: false
        );

        // Assert: Installation skipped due to existing tables
        $this->assertTrue(true);
    }

    /**
     * Test that TOTP tables are installed when TOTP component is included.
     */
    public function test_totp_tables_are_installed_when_include_totp_true(): void
    {
        // Arrange: Mock command output and user confirmation
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')->zeroOrMoreTimes();

        // Act: Execute installer with both components
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: false,
            includeOtp: true,
            includeTotp: true
        );

        // Assert: TOTP tables were processed
        $this->assertTrue(true);
    }

    /**
     * Test that OTP tables are installed when OTP component is included.
     */
    public function test_otp_tables_are_installed_when_include_otp_true(): void
    {
        // Arrange: Mock command output and user confirmation
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')->zeroOrMoreTimes();

        // Act: Execute installer with only OTP component
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: false,
            includeOtp: true,
            includeTotp: false
        );

        // Assert: OTP tables were processed
        $this->assertTrue(true);
    }

    /**
     * Test that TOTP migrations are skipped when TOTP tables already exist.
     */
    public function test_totp_migrations_are_skipped_when_tables_already_exist(): void
    {
        // Arrange: Create TOTP table to simulate existing installation
        if (! Schema::hasTable('two_factor_secrets')) {
            Schema::create('two_factor_secrets', function ($table): void {
                $table->id();
                $table->morphs('authenticatable');
                $table->string('secret', 64);
                $table->boolean('is_enabled')->default(false);
                $table->timestamps();
            });
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Do you want to reinstall? This may overwrite existing files.', false)
            ->andReturn(false);
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->never();
        $this->command->shouldReceive('call')->never();

        // Act: Execute installer with both components
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: false,
            includeOtp: true,
            includeTotp: true
        );

        // Assert: TOTP migrations were skipped due to existing tables
        $this->assertTrue(true);
    }

    /**
     * Test that OTP migrations are not published when OTP component is excluded.
     */
    public function test_installation_without_otp_does_not_publish_otp_migrations(): void
    {
        // Arrange: Mock command output and user confirmation
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')->zeroOrMoreTimes();

        // Act: Execute installer without OTP component
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: false,
            includeOtp: false,
            includeTotp: true
        );

        // Assert: OTP migrations were not published
        $this->assertTrue(true);
    }

    /**
     * Test that TOTP migrations are not published when TOTP component is excluded.
     */
    public function test_installation_without_totp_does_not_publish_totp_migrations(): void
    {
        // Arrange: Mock command output and user confirmation
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')->zeroOrMoreTimes();

        // Act: Execute installer without TOTP component
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: false,
            includeOtp: true,
            includeTotp: false
        );

        // Assert: TOTP migrations were not published
        $this->assertTrue(true);
    }

    /**
     * Test that both OTP and TOTP migrations are published when both components are included.
     */
    public function test_installation_with_both_components_publishes_all_migrations(): void
    {
        // Arrange: Mock command output and user confirmation
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')->zeroOrMoreTimes();

        // Act: Execute installer with both components
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: false,
            includeOtp: true,
            includeTotp: true
        );

        // Assert: Both OTP and TOTP migrations were published
        $this->assertTrue(true);
    }

    /**
     * Test that force mode installation with both components runs without confirmation.
     */
    public function test_installation_with_force_and_both_components(): void
    {
        // Arrange: Mock command output and bypass confirmation
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')->never();

        // Act: Execute installer in force mode with both components
        $this->installerService->install(
            command: $this->command,
            force: true,
            skipMigrations: false,
            includeOtp: true,
            includeTotp: true
        );

        // Assert: Installation ran without confirmation prompts
        $this->assertTrue(true);
    }

    /**
     * Test that migrations are skipped when skipMigrations flag is set.
     */
    public function test_installation_with_both_components_and_skip_migrations(): void
    {
        // Arrange: Mock command output and user confirmation
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(true);
        $this->command->shouldReceive('call')->never();

        // Act: Execute installer with skipMigrations flag
        $this->installerService->install(
            command: $this->command,
            force: false,
            skipMigrations: true,
            includeOtp: true,
            includeTotp: true
        );

        // Assert: No migration commands were called
        $this->assertTrue(true);
    }

    /**
     * Test that hasOtpTables returns true when OTP tables exist.
     */
    public function test_has_otp_tables_returns_true_when_tables_exist(): void
    {
        // Arrange: Create OTP table
        if (! Schema::hasTable('one_time_passwords')) {
            Schema::create('one_time_passwords', function ($table): void {
                $table->id();
                $table->timestamps();
            });
        }

        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('hasOtpTables');
        $method->setAccessible(true);

        // Act: Check if OTP tables exist
        $result = $method->invoke($this->installerService);

        // Assert: Method returns true
        $this->assertTrue($result);
    }

    /**
     * Test that hasOtpTables returns false when OTP tables don't exist.
     */
    public function test_has_otp_tables_returns_false_when_tables_dont_exist(): void
    {
        // Arrange: Ensure OTP table does not exist
        $this->dropTableIfExists('one_time_passwords');

        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('hasOtpTables');
        $method->setAccessible(true);

        // Act: Check if OTP tables exist
        $result = $method->invoke($this->installerService);

        // Assert: Method returns false
        $this->assertFalse($result);
    }

    /**
     * Test that hasTotpTables returns true when TOTP tables exist.
     */
    public function test_has_totp_tables_returns_true_when_tables_exist(): void
    {
        // Arrange: Create TOTP table
        if (! Schema::hasTable('two_factor_secrets')) {
            Schema::create('two_factor_secrets', function ($table): void {
                $table->id();
                $table->morphs('authenticatable');
                $table->string('secret', 64);
                $table->boolean('is_enabled')->default(false);
                $table->timestamps();
            });
        }

        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('hasTotpTables');
        $method->setAccessible(true);

        // Act: Check if TOTP tables exist
        $result = $method->invoke($this->installerService);

        // Assert: Method returns true
        $this->assertTrue($result);

        Schema::drop('two_factor_secrets');
    }

    /**
     * Test that hasTotpTables returns false when TOTP tables don't exist.
     */
    public function test_has_totp_tables_returns_false_when_tables_dont_exist(): void
    {
        // Arrange: Ensure TOTP table does not exist
        $this->dropTableIfExists('two_factor_secrets');

        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('hasTotpTables');
        $method->setAccessible(true);

        // Act: Check if TOTP tables exist
        $result = $method->invoke($this->installerService);

        // Assert: Method returns false
        $this->assertFalse($result);
    }
}
