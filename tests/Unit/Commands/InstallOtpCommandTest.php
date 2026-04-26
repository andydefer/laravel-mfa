<?php

declare(strict_types=1);

namespace Kani\Otp\Tests\Unit\Commands;

use Illuminate\Support\Facades\Artisan;
use Kani\Otp\Commands\InstallOtpCommand;
use Kani\Otp\Services\OtpInstallerService;
use Kani\Otp\Tests\TestCase;
use Mockery;

/**
 * Test suite for InstallOtpCommand.
 *
 * Verifies that the installation command correctly calls the
 * installer service with the appropriate parameters.
 */
final class InstallOtpCommandTest extends TestCase
{
    /**
     * Test that the command can be instantiated with correct properties.
     */
    public function test_command_can_be_instantiated(): void
    {
        // Arrange: Create command instance
        $command = new InstallOtpCommand();

        // Assert: Verify command name and description
        $this->assertSame('otp:install', $command->getName());
        $this->assertSame('Install the Laravel OTP package for one-time password management', $command->getDescription());
    }

    /**
     * Test that the command has correct signature options.
     */
    public function test_command_has_correct_signature(): void
    {
        // Arrange: Create command instance
        $command = new InstallOtpCommand();

        // Assert: Verify signature contains expected options
        $signature = $command->getSynopsis();
        $this->assertStringContainsString('--force', $signature);
        $this->assertStringContainsString('--no-migrate', $signature);
    }

    /**
     * Test that the command handles installation with force option.
     */
    public function test_handle_calls_installer_service_with_force_option(): void
    {
        // Arrange: Mock service expecting force flag = true and skipMigrations = false
        $mockService = Mockery::mock(OtpInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations): bool {
                return $command instanceof InstallOtpCommand
                    && $force === true
                    && $skipMigrations === false;
            });

        // Act: Register mock and execute command with force option
        $this->app->instance(OtpInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('otp:install', ['--force' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with no-migrate option.
     */
    public function test_handle_calls_installer_service_with_no_migrate_option(): void
    {
        // Arrange: Mock service expecting force flag = false and skipMigrations = true
        $mockService = Mockery::mock(OtpInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations): bool {
                return $command instanceof InstallOtpCommand
                    && $force === false
                    && $skipMigrations === true;
            });

        // Act: Register mock and execute command with no-migrate option
        $this->app->instance(OtpInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('otp:install', ['--no-migrate' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles installation with both force and no-migrate options.
     */
    public function test_handle_calls_installer_service_with_both_options(): void
    {
        // Arrange: Mock service expecting force flag = true and skipMigrations = true
        $mockService = Mockery::mock(OtpInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations): bool {
                return $command instanceof InstallOtpCommand
                    && $force === true
                    && $skipMigrations === true;
            });

        // Act: Register mock and execute command with both options
        $this->app->instance(OtpInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('otp:install', ['--force' => true, '--no-migrate' => true])
            ->assertExitCode(0);
    }

    /**
     * Test that the command passes itself to the installer service.
     */
    public function test_command_passes_itself_to_installer_service(): void
    {
        // Arrange: Mock service expecting the command instance
        $mockService = Mockery::mock(OtpInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations): bool {
                return $command instanceof InstallOtpCommand;
            });

        // Act: Register mock and execute command
        $this->app->instance(OtpInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('otp:install')
            ->assertExitCode(0);
    }

    /**
     * Test that the command returns success exit code.
     */
    public function test_command_returns_success_exit_code(): void
    {
        // Arrange: Mock service
        $mockService = Mockery::mock(OtpInstallerService::class);
        $mockService->shouldReceive('install')->once();

        // Act: Register mock and execute command
        $this->app->instance(OtpInstallerService::class, $mockService);
        $exitCode = Artisan::call('otp:install');

        // Assert: Exit code is 0 (success)
        $this->assertEquals(0, $exitCode);
    }

    /**
     * Test that the command handles installation without any options.
     */
    public function test_handle_calls_installer_service_without_options(): void
    {
        // Arrange: Mock service expecting force flag = false and skipMigrations = false
        $mockService = Mockery::mock(OtpInstallerService::class);
        $mockService->shouldReceive('install')
            ->once()
            ->withArgs(function ($command, $force, $skipMigrations): bool {
                return $command instanceof InstallOtpCommand
                    && $force === false
                    && $skipMigrations === false;
            });

        // Act: Register mock and execute command without any options
        $this->app->instance(OtpInstallerService::class, $mockService);

        // Assert: Command should execute successfully
        $this->artisan('otp:install')
            ->assertExitCode(0);
    }
}
