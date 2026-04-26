<?php
// tests/Unit/Commands/InstallMfaCommandTest.php

declare(strict_types=1);

namespace Kani\Mfa\Tests\Unit\Commands;

use Illuminate\Support\Facades\Artisan;
use Kani\Mfa\Core\Commands\InstallMfaCommand;
use Kani\Mfa\Core\Services\MfaInstallerService;
use Kani\Mfa\Tests\TestCase;
use Mockery;

/**
 * Test suite for InstallMfaCommand.
 *
 * Verifies that the installation command correctly calls the
 * installer service with the appropriate parameters.
 * By default, the command installs both OTP and TOTP components.
 */
final class InstallMfaCommandTest extends TestCase
{
    /**
     * Test that the command can be instantiated with correct properties.
     */
    public function test_command_can_be_instantiated(): void
    {
        // Arrange: Create command instance
        $command = new InstallMfaCommand();

        // Assert: Verify command name and description
        $this->assertSame('mfa:install', $command->getName());
        $this->assertSame('Install the Laravel MFA package for multi-factor authentication management (OTP + TOTP)', $command->getDescription());
    }

    /**
     * Test that the command has correct signature options.
     */
    public function test_command_has_correct_signature(): void
    {
        // Arrange: Create command instance
        $command = new InstallMfaCommand();

        // Assert: Verify signature contains expected options
        $signature = $command->getSynopsis();
        $this->assertStringContainsString('--force', $signature);
        $this->assertStringContainsString('--no-migrate', $signature);
        $this->assertStringContainsString('--without-otp', $signature);
        $this->assertStringContainsString('--without-totp', $signature);
    }

    /**
     * Test that the command handles installation with force option.
     */
    public function test_handle_calls_installer_service_with_force_option(): void
    {
        // Arrange: Mock service expecting force = true, skipMigrations = false, includeOtp = true, includeTotp = true
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === true
                    && $skipMigrations === false
                    && $includeOtp === true
                    && $includeTotp === true;
            });

        // Act: Register mock and execute command with force option
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', ['--force' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with no-migrate option.
     */
    public function test_handle_calls_installer_service_with_no_migrate_option(): void
    {
        // Arrange: Mock service expecting force = false, skipMigrations = true, includeOtp = true, includeTotp = true
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === false
                    && $skipMigrations === true
                    && $includeOtp === true
                    && $includeTotp === true;
            });

        // Act: Register mock and execute command with no-migrate option
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', ['--no-migrate' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation without OTP.
     */
    public function test_handle_calls_installer_service_without_otp_option(): void
    {
        // Arrange: Mock service expecting force = false, skipMigrations = false, includeOtp = false, includeTotp = true
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === false
                    && $skipMigrations === false
                    && $includeOtp === false
                    && $includeTotp === true;
            });

        // Act: Register mock and execute command with without-otp option
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', ['--without-otp' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation without TOTP.
     */
    public function test_handle_calls_installer_service_without_totp_option(): void
    {
        // Arrange: Mock service expecting force = false, skipMigrations = false, includeOtp = true, includeTotp = false
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === false
                    && $skipMigrations === false
                    && $includeOtp === true
                    && $includeTotp === false;
            });

        // Act: Register mock and execute command with without-totp option
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', ['--without-totp' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with force and without-otp options.
     */
    public function test_handle_calls_installer_service_with_force_and_without_otp_options(): void
    {
        // Arrange: Mock service expecting force = true, skipMigrations = false, includeOtp = false, includeTotp = true
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === true
                    && $skipMigrations === false
                    && $includeOtp === false
                    && $includeTotp === true;
            });

        // Act: Register mock and execute command with without-otp and force options
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', ['--without-otp' => true, '--force' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with force and without-totp options.
     */
    public function test_handle_calls_installer_service_with_force_and_without_totp_options(): void
    {
        // Arrange: Mock service expecting force = true, skipMigrations = false, includeOtp = true, includeTotp = false
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === true
                    && $skipMigrations === false
                    && $includeOtp === true
                    && $includeTotp === false;
            });

        // Act: Register mock and execute command with without-totp and force options
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', ['--without-totp' => true, '--force' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with all options combined.
     */
    public function test_handle_calls_installer_service_with_all_options(): void
    {
        // Arrange: Mock service expecting force = true, skipMigrations = true, includeOtp = false, includeTotp = false
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === true
                    && $skipMigrations === true
                    && $includeOtp === false
                    && $includeTotp === false;
            });

        // Act: Register mock and execute command with all options
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', [
            '--force' => true,
            '--no-migrate' => true,
            '--without-otp' => true,
            '--without-totp' => true,
        ])->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with force and no-migrate options.
     */
    public function test_handle_calls_installer_service_with_force_and_no_migrate_options(): void
    {
        // Arrange: Mock service expecting force = true, skipMigrations = true, includeOtp = true, includeTotp = true
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === true
                    && $skipMigrations === true
                    && $includeOtp === true
                    && $includeTotp === true;
            });

        // Act: Register mock and execute command with both options
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', ['--force' => true, '--no-migrate' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command passes itself to the installer service.
     */
    public function test_command_passes_itself_to_installer_service(): void
    {
        // Arrange: Mock service expecting the command instance
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand;
            });

        // Act: Register mock and execute command
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install')
            ->assertExitCode(0);
    }

    /**
     * Test that the command returns success exit code.
     */
    public function test_command_returns_success_exit_code(): void
    {
        // Arrange: Mock service
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')->once();

        // Act: Register mock and execute command
        $this->app->instance(MfaInstallerService::class, $mockService);
        $exitCode = Artisan::call('mfa:install');

        // Assert: Exit code is 0 (success)
        $this->assertEquals(0, $exitCode);
    }

    /**
     * Test that the command handles installation without any options (install both by default).
     */
    public function test_handle_calls_installer_service_without_options_installs_both_by_default(): void
    {
        // Arrange: Mock service expecting force = false, skipMigrations = false, includeOtp = true, includeTotp = true
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === false
                    && $skipMigrations === false
                    && $includeOtp === true
                    && $includeTotp === true;
            });

        // Act: Register mock and execute command without any options
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install')
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with both without-otp and without-totp options.
     */
    public function test_handle_calls_installer_service_with_both_without_options(): void
    {
        // Arrange: Mock service expecting force = false, skipMigrations = false, includeOtp = false, includeTotp = false
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === false
                    && $skipMigrations === false
                    && $includeOtp === false
                    && $includeTotp === false;
            });

        // Act: Register mock and execute command with both without options
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', ['--without-otp' => true, '--without-totp' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with all three options (force, no-migrate, without-otp).
     */
    public function test_handle_calls_installer_service_with_force_no_migrate_and_without_otp(): void
    {
        // Arrange: Mock service expecting force = true, skipMigrations = true, includeOtp = false, includeTotp = true
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === true
                    && $skipMigrations === true
                    && $includeOtp === false
                    && $includeTotp === true;
            });

        // Act: Register mock and execute command
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', [
            '--force' => true,
            '--no-migrate' => true,
            '--without-otp' => true,
        ])->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with all three options (force, no-migrate, without-totp).
     */
    public function test_handle_calls_installer_service_with_force_no_migrate_and_without_totp(): void
    {
        // Arrange: Mock service expecting force = true, skipMigrations = true, includeOtp = true, includeTotp = false
        $mockService = Mockery::mock(MfaInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations, $includeOtp, $includeTotp): bool {
                return $command instanceof InstallMfaCommand
                    && $force === true
                    && $skipMigrations === true
                    && $includeOtp === true
                    && $includeTotp === false;
            });

        // Act: Register mock and execute command
        $this->app->instance(MfaInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('mfa:install', [
            '--force' => true,
            '--no-migrate' => true,
            '--without-totp' => true,
        ])->assertExitCode(0);
    }
}
