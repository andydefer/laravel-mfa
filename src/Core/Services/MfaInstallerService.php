<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Core\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Installation service for the Laravel MFA package.
 *
 * Handles the complete installation process including publishing configuration
 * files, migrations, and running database migrations.
 * By default installs both OTP and TOTP components.
 */
class MfaInstallerService
{
    /**
     * OTP database tables required for one-time password functionality.
     *
     * @var array<int, string>
     */
    private const OTP_TABLES = [
        'one_time_passwords',
    ];

    /**
     * TOTP database tables required for two-factor authentication.
     *
     * @var array<int, string>
     */
    private const TOTP_TABLES = [
        'two_factor_secrets',
    ];

    /**
     * Execute the complete package installation.
     *
     * @param  Command  $command  The Artisan command instance for console output
     * @param  bool  $force  Whether to overwrite existing files without confirmation
     * @param  bool  $skipMigrations  Whether to skip running database migrations
     * @param  bool  $includeOtp  Whether to install OTP (default: true)
     * @param  bool  $includeTotp  Whether to install TOTP (default: true)
     */
    public function install(Command $command, bool $force = false, bool $skipMigrations = false, bool $includeOtp = true, bool $includeTotp = true): void
    {
        $this->displayWelcomeMessage($command);

        if (! $this->shouldProceedWithInstallation($command, $force, $includeOtp, $includeTotp)) {
            return;
        }

        $this->publishConfiguration($command, $force);
        $this->publishMigrations($command, $force, $includeOtp, $includeTotp);

        if (! $skipMigrations) {
            $this->handleDatabaseMigrations($command, $includeOtp, $includeTotp);
        }

        $this->displaySuccessMessage($command);
        $this->showQuickStartGuide($command, $includeOtp, $includeTotp);
    }

    /**
     * Display the package installation welcome message.
     *
     * @param  Command  $command  The console command for output
     */
    private function displayWelcomeMessage(Command $command): void
    {
        $command->info('🔐 Installing Laravel MFA package...');
        $command->newLine();
    }

    /**
     * Determine if installation should proceed based on current state and user confirmation.
     *
     * @param  Command  $command  The console command for output and input
     * @param  bool  $force  Whether to skip confirmation prompts
     * @param  bool  $includeOtp  Whether OTP is being installed
     * @param  bool  $includeTotp  Whether TOTP is being installed
     * @return bool True if installation should proceed, false otherwise
     */
    private function shouldProceedWithInstallation(Command $command, bool $force, bool $includeOtp, bool $includeTotp): bool
    {
        if ($force) {
            return true;
        }

        if ($this->isAlreadyInstalled($includeOtp, $includeTotp)) {
            $command->warn('⚠️ MFA package appears to be already installed.');

            if (! $command->confirm('Do you want to reinstall? This may overwrite existing files.', false)) {
                $command->info('Installation cancelled.');

                return false;
            }
        }

        $this->displayInstallationPlan($command, $includeOtp, $includeTotp);

        if (! $command->confirm('Continue with installation?', true)) {
            $command->info('Installation cancelled.');

            return false;
        }

        return true;
    }

    /**
     * Display the installation plan showing what files will be published.
     *
     * @param  Command  $command  The console command for output
     * @param  bool  $includeOtp  Whether OTP is being installed
     * @param  bool  $includeTotp  Whether TOTP is being installed
     */
    private function displayInstallationPlan(Command $command, bool $includeOtp, bool $includeTotp): void
    {
        $command->warn('📦 This will publish:');
        $command->line('   - Configuration (config/mfa.php)');

        if ($includeOtp) {
            $command->line('   - OTP migrations (one_time_passwords table)');
        }

        if ($includeTotp) {
            $command->line('   - TOTP migrations (two_factor_secrets table)');
        }

        $command->newLine();
    }

    /**
     * Check if the package has already been installed.
     *
     * @param  bool  $includeOtp  Whether to check OTP tables
     * @param  bool  $includeTotp  Whether to check TOTP tables
     * @return bool True if configuration exists or required tables are present
     */
    private function isAlreadyInstalled(bool $includeOtp, bool $includeTotp): bool
    {
        $configPath = config_path('mfa.php');

        if (File::exists($configPath)) {
            return true;
        }

        if ($includeOtp && $this->hasOtpTables()) {
            return true;
        }

        if ($includeTotp && $this->hasTotpTables()) {
            return true;
        }

        return false;
    }

    /**
     * Publish the package configuration file to the Laravel config directory.
     *
     * @param  Command  $command  The console command for output
     * @param  bool  $force  Whether to overwrite existing configuration file
     */
    private function publishConfiguration(Command $command, bool $force): void
    {
        $command->info('📝 Publishing configuration...');

        $sourcePath = $this->getConfigSourcePath();
        $destinationPath = config_path('mfa.php');

        $this->ensureDirectoryExists(dirname($destinationPath));

        if (File::exists($destinationPath) && ! $force) {
            $command->warn('   Config file already exists. Use --force to overwrite.');

            return;
        }

        File::copy($sourcePath, $destinationPath);
        $command->info('   ✅ Configuration published to config/mfa.php');
    }

    /**
     * Get the source path for the configuration file from the package.
     *
     * @return string Absolute path to the configuration file
     */
    private function getConfigSourcePath(): string
    {
        return __DIR__ . '/../../../config/mfa.php';
    }

    /**
     * Publish all package migration files to the Laravel migrations directory.
     *
     * @param  Command  $command  The console command for output
     * @param  bool  $force  Whether to overwrite existing migration files
     * @param  bool  $includeOtp  Whether to include OTP migrations
     * @param  bool  $includeTotp  Whether to include TOTP migrations
     */
    private function publishMigrations(Command $command, bool $force, bool $includeOtp, bool $includeTotp): void
    {
        $command->info('📄 Publishing migrations...');

        $migrationFiles = $this->getAllMigrationFiles($includeOtp, $includeTotp);

        if (empty($migrationFiles)) {
            $command->warn('   No migration files found to publish.');
            return;
        }

        $copiedCount = 0;

        foreach ($migrationFiles as $sourcePath) {
            $filename = basename($sourcePath);

            // Générer un nom de fichier avec un timestamp unique pour éviter les conflits
            // Le package utilise déjà des timestamps, on les garde
            $destinationPath = $this->getMigrationDestinationPath($filename);

            // Vérifier si le fichier source existe
            if (!File::exists($sourcePath)) {
                $command->warn("   Source migration not found: {$filename}");
                continue;
            }

            // Vérifier si le fichier destination existe déjà
            if (File::exists($destinationPath) && ! $force) {
                $command->warn("   Migration {$filename} already exists. Use --force to overwrite.");
                continue;
            }

            $this->ensureDirectoryExists(dirname($destinationPath));

            // Copier le fichier
            if (File::copy($sourcePath, $destinationPath)) {
                $copiedCount++;
                $command->line("   ✅ Copied: {$filename}");
            } else {
                $command->error("   ❌ Failed to copy: {$filename}");
            }
        }

        if ($copiedCount > 0) {
            $command->info("   ✅ {$copiedCount} migration(s) published to database/migrations/");
        } else {
            $command->warn("   No new migrations were copied.");
        }
    }

    /**
     * Get all migration files from the package's migration directory.
     *
     * @param  bool  $includeOtp  If false, excludes OTP migration files
     * @param  bool  $includeTotp  If false, excludes TOTP migration files
     * @return array<int, string> List of migration file paths
     */
    private function getAllMigrationFiles(bool $includeOtp, bool $includeTotp): array
    {
        $migrationDirectory = __DIR__ . '/../../../database/migrations/';

        if (! is_dir($migrationDirectory)) {
            return [];
        }

        // Récupérer tous les fichiers PHP dans le dossier migrations
        $files = glob($migrationDirectory . '*.php');

        if ($files === false || empty($files)) {
            return [];
        }

        $filteredFiles = array_filter($files, function ($file) use ($includeOtp, $includeTotp) {
            $filename = basename($file);
            $isOtpMigration = str_contains($filename, 'one_time_passwords');
            $isTotpMigration = str_contains($filename, 'two_factor_secrets');

            // Exclure les migrations OTP si requested
            if ($isOtpMigration && ! $includeOtp) {
                return false;
            }

            // Exclure les migrations TOTP si requested
            if ($isTotpMigration && ! $includeTotp) {
                return false;
            }

            return true;
        });

        return array_values($filteredFiles);
    }

    /**
     * Determine the destination path for a migration file in the Laravel project.
     *
     * @param  string  $filename  The migration filename
     * @return string Destination path in database/migrations directory
     */
    private function getMigrationDestinationPath(string $filename): string
    {
        return database_path('migrations/' . $filename);
    }

    /**
     * Ensure a directory exists, creating it recursively with proper permissions.
     *
     * @param  string  $directoryPath  Path to the directory to create
     */
    private function ensureDirectoryExists(string $directoryPath): void
    {
        if (! File::exists($directoryPath)) {
            File::makeDirectory($directoryPath, 0755, true);
        }
    }

    /**
     * Run the database migrations for the package.
     *
     * @param  Command  $command  The console command for output
     * @param  bool  $includeOtp  Whether OTP tables should be checked for existence
     * @param  bool  $includeTotp  Whether TOTP tables should be checked for existence
     */
    private function handleDatabaseMigrations(Command $command, bool $includeOtp, bool $includeTotp): void
    {
        $hasExistingTables = false;

        if ($includeOtp && $this->hasOtpTables()) {
            $command->warn('⚠️ OTP tables already exist. Skipping OTP migrations.');
            $hasExistingTables = true;
        }

        if ($includeTotp && $this->hasTotpTables()) {
            $command->warn('⚠️ TOTP tables already exist. Skipping TOTP migrations.');
            $hasExistingTables = true;
        }

        if ($hasExistingTables && ! $command->confirm('Tables already exist. Continue with migrations for missing tables?', true)) {
            $command->info('Migrations skipped.');

            return;
        }

        $command->info('🗄️ Running migrations...');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            if (!empty($output)) {
                $command->line($output);
            }
            $command->info('   ✅ Migrations completed successfully.');
        } catch (RuntimeException $exception) {
            $command->error('   ❌ Migration failed: ' . $exception->getMessage());
        }
    }

    /**
     * Check if the OTP database tables are present.
     *
     * @return bool True if any OTP table exists
     */
    private function hasOtpTables(): bool
    {
        try {
            foreach (self::OTP_TABLES as $tableName) {
                if (Schema::hasTable($tableName)) {
                    return true;
                }
            }
        } catch (RuntimeException $exception) {
            // Database connection may not be configured yet
            return false;
        }

        return false;
    }

    /**
     * Check if the TOTP database tables are present.
     *
     * @return bool True if any TOTP table exists
     */
    private function hasTotpTables(): bool
    {
        try {
            foreach (self::TOTP_TABLES as $tableName) {
                if (Schema::hasTable($tableName)) {
                    return true;
                }
            }
        } catch (RuntimeException $exception) {
            // Database connection may not be configured yet
            return false;
        }

        return false;
    }

    /**
     * Display the installation success message with visual separator.
     *
     * @param  Command  $command  The console command for output
     */
    private function displaySuccessMessage(Command $command): void
    {
        $command->newLine();
        $command->info('═══════════════════════════════════════════════════════');
        $command->info('✅ Laravel MFA package installed successfully!');
        $command->info('═══════════════════════════════════════════════════════');
    }

    /**
     * Display the quick start guide with usage examples for developers.
     *
     * @param  Command  $command  The console command for output
     * @param  bool  $includeOtp  Whether OTP was installed
     * @param  bool  $includeTotp  Whether TOTP was installed
     */
    private function showQuickStartGuide(Command $command, bool $includeOtp, bool $includeTotp): void
    {
        $command->newLine();
        $command->line('📚 Quick Start Guide:');
        $command->line('');

        if ($includeOtp) {
            $command->line('   🔑 OTP (One-Time Password):');
            $command->line('      1. Add the trait to your model:');
            $command->line('         <info>use AndyDefer\\Mfa\\Otp\\Traits\\HasOneTimePasswords;</info>');
            $command->line('');
            $command->line('      2. Send an OTP:');
            $command->line('         <info>$user->sendOtp("email_verification", "user@example.com", "email");</info>');
            $command->line('');
            $command->line('      3. Verify the OTP:');
            $command->line('         <info>$result = $user->verifyOtp($request->code, "email_verification");</info>');
            $command->line('');
        }

        if ($includeTotp) {
            $command->line('   🔐 TOTP (Time-based One-Time Password / 2FA):');
            $command->line('      1. Add the trait to your model:');
            $command->line('         <info>use AndyDefer\\Mfa\\Totp\\Traits\\HasTwoFactorAuthentication;</info>');
            $command->line('');
            $command->line('      2. Generate a QR code for Google Authenticator:');
            $command->line('         <info>$qrCodeUri = $user->getTwoFactorQrCodeUri();</info>');
            $command->line('');
            $command->line('      3. Enable 2FA after code verification:');
            $command->line('         <info>$enabled = $user->enableTwoFactor($code);</info>');
            $command->line('');
        }

        $command->line('   🧹 Clean up expired OTPs:');
        $command->line('      <info>php artisan mfa:cleanup</info>');
        $command->line('');
        $command->line('📖 Documentation: https://github.com/andydefer/laravel-mfa');
    }
}
