<?php

declare(strict_types=1);

namespace Kani\Otp\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Service for installing and setting up the Laravel OTP package.
 *
 * Handles the complete installation process including:
 * - Publishing configuration files
 * - Publishing database migrations
 * - Running database migrations
 * - Validating existing installations
 */
final class OtpInstallerService
{
    /**
     * Core tables required by the OTP package.
     *
     * @var array<int, string>
     */
    private const CORE_TABLES = [
        'one_time_passwords',
    ];

    /**
     * Execute the complete OTP package installation process.
     *
     * @param Command $command The console command instance for output and input
     * @param bool    $force   Force overwrite existing files
     * @param bool    $skipMigrations Skip database migrations
     */
    public function install(Command $command, bool $force = false, bool $skipMigrations = false): void
    {
        $command->info('🔐 Installing Laravel OTP package...');
        $command->newLine();

        if (!$this->shouldProceedWithInstallation($command, $force)) {
            return;
        }

        $this->publishConfiguration($command, $force);
        $this->publishMigrations($command, $force);

        if (!$skipMigrations) {
            $this->handleDatabaseMigrations($command);
        }

        $this->displaySuccessMessage($command);
        $this->showQuickStartGuide($command);
    }

    /**
     * Determine if the installation should proceed.
     *
     * Checks for existing installation and confirms with the user
     * unless force mode is enabled.
     */
    private function shouldProceedWithInstallation(Command $command, bool $force): bool
    {
        if ($force) {
            return true;
        }

        if ($this->isAlreadyInstalled()) {
            $command->warn('⚠️ OTP package appears to be already installed.');

            if (!$command->confirm('Do you want to reinstall? This may overwrite existing files.', false)) {
                $command->info('Installation cancelled.');

                return false;
            }
        }

        $this->displayInstallationPlan($command);

        if (!$command->confirm('Continue with installation?', true)) {
            $command->info('Installation cancelled.');

            return false;
        }

        return true;
    }

    /**
     * Display what will be installed.
     */
    private function displayInstallationPlan(Command $command): void
    {
        $command->warn('📦 This will publish:');
        $command->line('   - Configuration (config/otp.php)');
        $command->line('   - Database migration (one_time_passwords table)');
        $command->newLine();
    }

    /**
     * Check if the package is already installed.
     *
     * Verifies by checking for config file existence or database tables.
     */
    private function isAlreadyInstalled(): bool
    {
        return File::exists(config_path('otp.php')) || $this->hasCoreTables();
    }

    /**
     * Publish the configuration file.
     */
    private function publishConfiguration(Command $command, bool $force): void
    {
        $command->info('📝 Publishing configuration...');

        $sourcePath = $this->getConfigSourcePath();
        $destinationPath = config_path('otp.php');

        if ($this->shouldSkipFileCopy($destinationPath, $force)) {
            $command->warn('   Config file already exists. Use --force to overwrite.');

            return;
        }

        $this->ensureDirectoryExists(dirname($destinationPath));
        File::copy($sourcePath, $destinationPath);

        $command->info('   ✅ Configuration published to config/otp.php');
    }

    /**
     * Get the source path for the configuration file.
     */
    private function getConfigSourcePath(): string
    {
        return __DIR__ . '/../../config/otp.php';
    }

    /**
     * Publish the migration files.
     */
    private function publishMigrations(Command $command, bool $force): void
    {
        $command->info('📄 Publishing migrations...');

        $destinationPath = $this->getMigrationDestinationPath();
        $sourcePath = $this->getMigrationSourcePath();

        if ($this->shouldSkipFileCopy($destinationPath, $force)) {
            $command->warn('   Migration already exists. Use --force to overwrite.');

            return;
        }

        $this->ensureDirectoryExists(dirname($destinationPath));
        File::copy($sourcePath, $destinationPath);

        $command->info('   ✅ Migration published to database/migrations/');
    }

    /**
     * Get the destination path for the migration file.
     */
    private function getMigrationDestinationPath(): string
    {
        return database_path('migrations/' . $this->generateMigrationFileName());
    }

    /**
     * Get the source path for the migration file.
     */
    private function getMigrationSourcePath(): string
    {
        return __DIR__ . '/../../database/migrations/create_one_time_passwords_table.php';
    }

    /**
     * Generate a unique migration file name with current timestamp.
     */
    private function generateMigrationFileName(): string
    {
        $timestamp = date('Y_m_d_His');

        return "{$timestamp}_create_one_time_passwords_table.php";
    }

    /**
     * Check if a file copy should be skipped.
     */
    private function shouldSkipFileCopy(string $destinationPath, bool $force): bool
    {
        return File::exists($destinationPath) && !$force;
    }

    /**
     * Create a directory if it doesn't exist.
     */
    private function ensureDirectoryExists(string $directoryPath): void
    {
        if (!File::exists($directoryPath)) {
            File::makeDirectory($directoryPath, 0755, true);
        }
    }

    /**
     * Run database migrations.
     */
    private function handleDatabaseMigrations(Command $command): void
    {
        if ($this->hasCoreTables()) {
            $command->warn('⚠️ OTP tables already exist. Skipping migrations.');

            return;
        }

        $command->info('🗄️ Running migrations...');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $command->info(Artisan::output());
            $command->info('   ✅ Migrations completed successfully.');
        } catch (\Exception $exception) {
            $command->error('   ❌ Migration failed: ' . $exception->getMessage());
        }
    }

    /**
     * Check if any core OTP tables already exist in the database.
     */
    private function hasCoreTables(): bool
    {
        try {
            foreach (self::CORE_TABLES as $tableName) {
                if (Schema::hasTable($tableName)) {
                    return true;
                }
            }
        } catch (\Exception $exception) {
            return false;
        }

        return false;
    }

    /**
     * Display the installation success message.
     */
    private function displaySuccessMessage(Command $command): void
    {
        $command->newLine();
        $command->info('═══════════════════════════════════════════════════════');
        $command->info('✅ Laravel OTP package installed successfully!');
        $command->info('═══════════════════════════════════════════════════════');
    }

    /**
     * Display the quick start guide with usage examples.
     */
    private function showQuickStartGuide(Command $command): void
    {
        $command->newLine();
        $command->line('📚 Quick Start Guide:');
        $command->line('');
        $command->line('   1. Add the HasOneTimePasswords trait to your model:');
        $command->line('      <info>use Kani\\Otp\\Traits\\HasOneTimePasswords;</info>');
        $command->line('');
        $command->line('   2. Send an OTP to your user:');
        $command->line('      <info>$user->sendOtp("email_verification", "user@example.com", "email");</info>');
        $command->line('');
        $command->line('   3. Verify the OTP:');
        $command->line('      <info>$result = $user->verifyOtp($request->code, "email_verification");</info>');
        $command->line('');
        $command->line('   4. Clean up expired OTPs:');
        $command->line('      <info>php artisan otp:cleanup</info>');
        $command->line('');
        $command->line('📖 Documentation: https://github.com/andydefer/laravel-otp');
    }
}
