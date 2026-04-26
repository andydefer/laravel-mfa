<?php

declare(strict_types=1);

namespace Kani\Otp\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Installation service for the Laravel OTP package.
 * 
 * Handles the complete installation process including publishing configuration
 * files, migrations, and running database migrations.
 */
class OtpInstallerService
{
    /**
     * Core database tables required for OTP functionality.
     *
     * @var array<int, string>
     */
    private const CORE_TABLES = [
        'one_time_passwords',
    ];

    /**
     * Execute the complete package installation.
     *
     * @param Command $command The Artisan command instance for console output
     * @param bool $force Whether to overwrite existing files
     * @param bool $skipMigrations Whether to skip running database migrations
     */
    public function install(Command $command, bool $force = false, bool $skipMigrations = false): void
    {
        $this->displayWelcomeMessage($command);

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
     * Display the package installation welcome message.
     *
     * @param Command $command The Artisan command instance
     */
    private function displayWelcomeMessage(Command $command): void
    {
        $command->info('🔐 Installing Laravel OTP package...');
        $command->newLine();
    }

    /**
     * Check if installation should proceed based on current state and user confirmation.
     *
     * @param Command $command The Artisan command instance
     * @param bool $force Whether to force installation without confirmation
     * @return bool True if installation should proceed, false otherwise
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
     * Display the installation plan showing what files will be published.
     *
     * @param Command $command The Artisan command instance
     */
    private function displayInstallationPlan(Command $command): void
    {
        $command->warn('📦 This will publish:');
        $command->line('   - Configuration (config/otp.php)');
        $command->line('   - Database migrations (one_time_passwords table)');
        $command->newLine();
    }

    /**
     * Check if the package has already been installed.
     *
     * @return bool True if configuration exists or core tables are present
     */
    private function isAlreadyInstalled(): bool
    {
        $configPath = config_path('otp.php');

        return File::exists($configPath) || $this->hasCoreTables();
    }

    /**
     * Publish the package configuration file.
     *
     * @param Command $command The Artisan command instance
     * @param bool $force Whether to overwrite existing configuration file
     */
    private function publishConfiguration(Command $command, bool $force): void
    {
        $command->info('📝 Publishing configuration...');

        $sourcePath = $this->getConfigSourcePath();
        $destinationPath = config_path('otp.php');

        $this->ensureDirectoryExists(dirname($destinationPath));

        if (File::exists($destinationPath) && !$force) {
            $command->warn('   Config file already exists. Use --force to overwrite.');
            return;
        }

        File::copy($sourcePath, $destinationPath);
        $command->info('   ✅ Configuration published to config/otp.php');
    }

    /**
     * Get the source path for the configuration file.
     *
     * @return string Absolute path to the configuration file
     */
    private function getConfigSourcePath(): string
    {
        return __DIR__ . '/../../config/otp.php';
    }

    /**
     * Publish all package migration files.
     *
     * @param Command $command The Artisan command instance
     * @param bool $force Whether to overwrite existing migration files
     */
    private function publishMigrations(Command $command, bool $force): void
    {
        $command->info('📄 Publishing migrations...');

        $migrationFiles = $this->getAllMigrationFiles();

        foreach ($migrationFiles as $sourcePath) {
            $destinationPath = $this->getMigrationDestinationPath($sourcePath);

            if (File::exists($destinationPath) && !$force) {
                $command->warn("   Migration " . basename($sourcePath) . " already exists. Use --force to overwrite.");
                continue;
            }

            $this->ensureDirectoryExists(dirname($destinationPath));
            File::copy($sourcePath, $destinationPath);
        }

        $command->info('   ✅ Migrations published to database/migrations/');
    }

    /**
     * Get all migration files from the package.
     *
     * @return array<int, string> List of migration file paths
     */
    private function getAllMigrationFiles(): array
    {
        $migrationDirectory = __DIR__ . '/../../database/migrations/';
        $migrationFiles = glob($migrationDirectory . '*.php');

        return $migrationFiles === false ? [] : $migrationFiles;
    }

    /**
     * Determine the destination path for a migration file.
     *
     * @param string $sourcePath Source migration file path
     * @return string Destination path in the Laravel project
     */
    private function getMigrationDestinationPath(string $sourcePath): string
    {
        $filename = basename($sourcePath);
        return database_path('migrations/' . $filename);
    }

    /**
     * Ensure a directory exists, creating it recursively if needed.
     *
     * @param string $directoryPath Path to the directory
     */
    private function ensureDirectoryExists(string $directoryPath): void
    {
        if (!File::exists($directoryPath)) {
            File::makeDirectory($directoryPath, 0755, true);
        }
    }

    /**
     * Run the database migrations for the package.
     *
     * @param Command $command The Artisan command instance
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
        } catch (RuntimeException $exception) {
            $command->error('   ❌ Migration failed: ' . $exception->getMessage());
        }
    }

    /**
     * Check if the core database tables are present.
     * 
     * This method name is preserved for test compatibility.
     *
     * @return bool True if any core table exists in the database
     */
    private function hasCoreTables(): bool
    {
        try {
            foreach (self::CORE_TABLES as $tableName) {
                if (Schema::hasTable($tableName)) {
                    return true;
                }
            }
        } catch (RuntimeException $exception) {
            return false;
        }

        return false;
    }

    /**
     * Display the installation success message.
     *
     * @param Command $command The Artisan command instance
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
     *
     * @param Command $command The Artisan command instance
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
