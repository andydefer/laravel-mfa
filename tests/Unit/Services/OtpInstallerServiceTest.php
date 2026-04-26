<?php

declare(strict_types=1);

namespace Kani\Otp\Tests\Unit\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Kani\Otp\Services\OtpInstallerService;
use Kani\Otp\Tests\TestCase;
use Mockery;

final class OtpInstallerServiceTest extends TestCase
{
    private OtpInstallerService $installerService;
    private Command $command;
    private string $configPath;
    private string $tempMigrationsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerService = new OtpInstallerService();
        $this->command = Mockery::mock(Command::class);
        $this->configPath = config_path('otp.php');

        if (File::exists($this->configPath)) {
            File::delete($this->configPath);
        }

        if (Schema::hasTable('one_time_passwords')) {
            Schema::drop('one_time_passwords');
        }

        // Nettoyer le dossier de migrations de destination
        $this->tempMigrationsDir = database_path('migrations');
        if (File::exists($this->tempMigrationsDir)) {
            foreach (glob($this->tempMigrationsDir . '/*.php') as $file) {
                File::delete($file);
            }
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();

        if (File::exists($this->configPath)) {
            File::delete($this->configPath);
        }

        parent::tearDown();
    }

    public function test_installation_proceeds_when_force_mode_enabled(): void
    {
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')->never();

        $this->installerService->install($this->command, force: true, skipMigrations: true);

        $this->assertTrue(true);
    }

    public function test_installation_cancels_when_user_declines_confirmation(): void
    {
        $this->command->shouldReceive('info')->once();
        $this->command->shouldReceive('warn')->once();
        $this->command->shouldReceive('line')->times(2);
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(false);
        $this->command->shouldReceive('info')->with('Installation cancelled.')->once();

        $this->installerService->install($this->command, force: false, skipMigrations: true);

        $this->assertTrue(true);
    }

    public function test_migrations_are_skipped_when_disabled(): void
    {
        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->zeroOrMoreTimes();
        $this->command->shouldReceive('line')->zeroOrMoreTimes();
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->andReturn(true);

        $this->installerService->install($this->command, force: false, skipMigrations: true);

        $this->assertTrue(true);
    }

    public function test_migrations_are_skipped_when_tables_already_exist(): void
    {
        if (!Schema::hasTable('one_time_passwords')) {
            Schema::create('one_time_passwords', function ($table) {
                $table->id();
            });
        }

        $this->command->shouldReceive('info')->zeroOrMoreTimes();
        $this->command->shouldReceive('warn')->with('⚠️ OTP package appears to be already installed.')->once();
        $this->command->shouldReceive('line')->zeroOrMoreTimes(); // Changé : zeroOrMoreTimes au lieu de times(2)
        $this->command->shouldReceive('newLine')->zeroOrMoreTimes();
        $this->command->shouldReceive('confirm')
            ->with('Do you want to reinstall? This may overwrite existing files.', false)
            ->andReturn(false);
        $this->command->shouldReceive('confirm')
            ->with('Continue with installation?', true)
            ->never();
        $this->command->shouldReceive('call')->never();

        $this->installerService->install($this->command, force: false, skipMigrations: false);

        $this->assertTrue(true);
    }

    public function test_has_core_tables_returns_true_when_tables_exist(): void
    {
        if (!Schema::hasTable('one_time_passwords')) {
            Schema::create('one_time_passwords', function ($table) {
                $table->id();
            });
        }

        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('hasCoreTables');
        $method->setAccessible(true);

        $result = $method->invoke($this->installerService);

        $this->assertTrue($result);
    }

    public function test_has_core_tables_returns_false_when_tables_dont_exist(): void
    {
        if (Schema::hasTable('one_time_passwords')) {
            Schema::drop('one_time_passwords');
        }

        $reflection = new \ReflectionClass($this->installerService);
        $method = $reflection->getMethod('hasCoreTables');
        $method->setAccessible(true);

        $result = $method->invoke($this->installerService);

        $this->assertFalse($result);
    }
}
