<?php

// src/Directives/InstallMfaDirective.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Enums\PermissionMode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\FileSystemService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Builder;

/**
 * Console directive for installing the Laravel MFA package.
 *
 * By default installs both OTP and TOTP (2FA) systems.
 * Users can choose to skip specific components using the available options.
 */
final class InstallMfaDirective extends AbstractDirective
{
    /**
     * OTP database tables required for one-time password functionality.
     */
    private const OTP_TABLES = [
        'one_time_passwords',
    ];

    /**
     * TOTP database tables required for two-factor authentication.
     */
    private const TOTP_TABLES = [
        'two_factor_secrets',
    ];

    public function __construct(
        DirectiveContext $context,
        DirectiveInteractionService $interaction,
        private readonly Kernel $kernel,
        private readonly Application $app,
        private readonly FileSystemService $filesystem,
        private readonly DatabaseManager $db,
    ) {
        parent::__construct($context, $interaction);
    }

    public function getSignature(): string
    {
        return 'mfa-install 
                {--force : Force publishing without confirmation prompt}
                {--no-migrate : Skip database migrations after publishing}
                {--without-otp : Skip OTP (email/sms one-time passwords) installation}
                {--without-totp : Skip TOTP (Google Authenticator 2FA) installation}';
    }

    public function getDescription(): string
    {
        return 'Install the Laravel MFA package for multi-factor authentication management (OTP + TOTP)';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('mfa-setup');
        $aliases->add('mfa-configure');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    private function getSchemaBuilder(): Builder
    {
        $connection = $this->db->connection();

        return $connection->getSchemaBuilder();
    }

    public function execute(): ExitCode
    {
        $force = $this->hasOption('force');
        $skipMigrations = $this->hasOption('no-migrate');
        $includeOtp = ! $this->hasOption('without-otp');
        $includeTotp = ! $this->hasOption('without-totp');

        $this->displayWelcomeMessage();

        if (! $this->shouldProceedWithInstallation($force, $includeOtp, $includeTotp)) {
            return ExitCode::SUCCESS;
        }

        // ========================================================================
        // 1. VÉRIFIER QUE LE PACKAGE EXISTE
        // ========================================================================
        $packageRoot = $this->app->basePath().'/vendor/andydefer/laravel-mfa';

        $this->info("\n📦 Checking package files...");

        if (! $this->filesystem->exists($packageRoot)) {
            $this->error("Package not found at: {$packageRoot}");
            $this->error('Please run: composer require andydefer/laravel-mfa');

            return ExitCode::FAILURE;
        }
        $this->info('  ✓ Package found');

        // ========================================================================
        // 2. COPIER LA CONFIGURATION
        // ========================================================================
        $this->info("\n📄 Publishing configuration...");

        $configSource = $packageRoot.'/config/mfa.php';
        $configDestination = $this->app->basePath('config/mfa.php');

        if (! $this->filesystem->exists($configSource)) {
            $this->error("Config source not found: {$configSource}");

            return ExitCode::FAILURE;
        }

        if ($this->filesystem->exists($configDestination) && ! $force) {
            $this->warn('   Config already exists, use --force to overwrite');
        } else {
            $this->ensureDirectoryExists(dirname($configDestination));
            $content = $this->filesystem->get($configSource);
            $this->filesystem->put($configDestination, $content);
            $this->info('  ✓ Configuration published to config/mfa.php');
        }

        // ========================================================================
        // 3. COPIER LES MIGRATIONS
        // ========================================================================
        $this->info("\n📄 Publishing migrations...");

        $migrationFiles = $this->getAllMigrationFiles($packageRoot, $includeOtp, $includeTotp);

        if (empty($migrationFiles)) {
            $this->warn('   No migration files found to publish.');
        } else {
            $copiedCount = 0;

            foreach ($migrationFiles as $sourcePath) {
                $filename = basename($sourcePath);
                $destinationPath = $this->app->databasePath('migrations/'.$filename);

                if ($this->filesystem->exists($destinationPath) && ! $force) {
                    $this->warn("   Migration {$filename} already exists, use --force to overwrite");

                    continue;
                }

                $this->ensureDirectoryExists(dirname($destinationPath));
                $content = $this->filesystem->get($sourcePath);
                $this->filesystem->put($destinationPath, $content);
                $copiedCount++;
                $this->line("   ✅ Copied: {$filename}");
            }

            $this->info("   ✅ {$copiedCount} migration(s) published to database/migrations/");
        }

        // ========================================================================
        // 4. LANCER LES MIGRATIONS VIA ARTISAN
        // ========================================================================
        if (! $skipMigrations) {
            $this->info("\n🗄️ Running migrations...");

            $exitCode = $this->kernel->call('migrate', ['--force' => true]);

            if ($exitCode !== 0) {
                $this->error('   ❌ Failed to run migrations.');

                return ExitCode::FAILURE;
            }
            $this->info('   ✅ Migrations executed');
        } else {
            $this->info("\n⏭️ Skipping migrations (--no-migrate flag used)");
        }

        // ========================================================================
        // 5. VÉRIFIER LES TABLES (si migrations exécutées)
        // ========================================================================
        if (! $skipMigrations) {
            $this->info("\n✅ Verifying database tables...");
            $schemaBuilder = $this->getSchemaBuilder();

            $this->verifyTables($schemaBuilder, $includeOtp, $includeTotp);
        }

        // ========================================================================
        // 6. INSTALLATION FINALE
        // ========================================================================
        $this->displaySuccessMessage();
        $this->showQuickStartGuide($includeOtp, $includeTotp);

        return ExitCode::SUCCESS;
    }

    private function displayWelcomeMessage(): void
    {
        $this->info('🔐 Installing Laravel MFA package...');
        $this->newLine();
    }

    private function shouldProceedWithInstallation(bool $force, bool $includeOtp, bool $includeTotp): bool
    {
        if ($force) {
            return true;
        }

        if ($this->isAlreadyInstalled($includeOtp, $includeTotp)) {
            $this->warn('⚠️ MFA package appears to be already installed.');

            if (! $this->confirm('Do you want to reinstall? This may overwrite existing files.', false)) {
                $this->info('Installation cancelled.');

                return false;
            }
        }

        $this->displayInstallationPlan($includeOtp, $includeTotp);

        if (! $this->confirm('Continue with installation?', true)) {
            $this->info('Installation cancelled.');

            return false;
        }

        return true;
    }

    private function displayInstallationPlan(bool $includeOtp, bool $includeTotp): void
    {
        $this->warn('📦 This will publish:');
        $this->line('   - Configuration (config/mfa.php)');

        if ($includeOtp) {
            $this->line('   - OTP migrations (one_time_passwords table)');
        }

        if ($includeTotp) {
            $this->line('   - TOTP migrations (two_factor_secrets table)');
        }

        $this->newLine();
    }

    private function isAlreadyInstalled(bool $includeOtp, bool $includeTotp): bool
    {
        $configPath = $this->app->basePath('config/mfa.php');

        if ($this->filesystem->exists($configPath)) {
            return true;
        }

        $schemaBuilder = $this->getSchemaBuilder();

        if ($includeOtp && $this->hasOtpTables($schemaBuilder)) {
            return true;
        }

        if ($includeTotp && $this->hasTotpTables($schemaBuilder)) {
            return true;
        }

        return false;
    }

    private function getAllMigrationFiles(string $packageRoot, bool $includeOtp, bool $includeTotp): array
    {
        $migrationDirectory = $packageRoot.'/database/migrations/';

        if (! $this->filesystem->isDirectory($migrationDirectory)) {
            return [];
        }

        $files = $this->filesystem->glob($migrationDirectory.'*.php');

        if (empty($files)) {
            return [];
        }

        $filteredFiles = array_filter($files, function ($file) use ($includeOtp, $includeTotp) {
            $filename = basename($file);
            $isOtpMigration = str_contains($filename, 'one_time_passwords');
            $isTotpMigration = str_contains($filename, 'two_factor_secrets');

            if ($isOtpMigration && ! $includeOtp) {
                return false;
            }

            if ($isTotpMigration && ! $includeTotp) {
                return false;
            }

            return true;
        });

        return array_values($filteredFiles);
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! $this->filesystem->isDirectory($path)) {
            $this->filesystem->makeDirectory($path, PermissionMode::DIRECTORY, true);
        }
    }

    private function verifyTables(Builder $schemaBuilder, bool $includeOtp, bool $includeTotp): void
    {
        if ($includeOtp) {
            if ($schemaBuilder->hasTable('one_time_passwords')) {
                $this->info('  ✓ Table "one_time_passwords" exists');
            } else {
                $this->warn('  ⚠️ Table "one_time_passwords" not found');
            }
        }

        if ($includeTotp) {
            if ($schemaBuilder->hasTable('two_factor_secrets')) {
                $this->info('  ✓ Table "two_factor_secrets" exists');
            } else {
                $this->warn('  ⚠️ Table "two_factor_secrets" not found');
            }
        }
    }

    private function hasOtpTables(Builder $schemaBuilder): bool
    {
        foreach (self::OTP_TABLES as $tableName) {
            if ($schemaBuilder->hasTable($tableName)) {
                return true;
            }
        }

        return false;
    }

    private function hasTotpTables(Builder $schemaBuilder): bool
    {
        foreach (self::TOTP_TABLES as $tableName) {
            if ($schemaBuilder->hasTable($tableName)) {
                return true;
            }
        }

        return false;
    }

    private function displaySuccessMessage(): void
    {
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════');
        $this->info('✅ Laravel MFA package installed successfully!');
        $this->line('═══════════════════════════════════════════════════════');
    }

    private function showQuickStartGuide(bool $includeOtp, bool $includeTotp): void
    {
        $this->newLine();
        $this->line('📚 Quick Start Guide:');
        $this->line('');

        if ($includeOtp) {
            $this->line('   🔑 OTP (One-Time Password):');
            $this->line('      1. Add the trait to your model:');
            $this->line('         <info>use AndyDefer\\Mfa\\Otp\\Traits\\HasOneTimePasswords;</info>');
            $this->line('');
            $this->line('      2. Send an OTP:');
            $this->line('         <info>$user->sendOtp("email_verification", "user@example.com", "email");</info>');
            $this->line('');
            $this->line('      3. Verify the OTP:');
            $this->line('         <info>$result = $user->verifyOtp($request->code, "email_verification");</info>');
            $this->line('');
        }

        if ($includeTotp) {
            $this->line('   🔐 TOTP (Time-based One-Time Password / 2FA):');
            $this->line('      1. Add the trait to your model:');
            $this->line('         <info>use AndyDefer\\Mfa\\Totp\\Traits\\HasTwoFactorAuthentication;</info>');
            $this->line('');
            $this->line('      2. Generate a QR code for Google Authenticator:');
            $this->line('         <info>$qrCodeUri = $user->getTwoFactorQrCodeUri();</info>');
            $this->line('');
            $this->line('      3. Enable 2FA after code verification:');
            $this->line('         <info>$enabled = $user->enableTwoFactor($code);</info>');
            $this->line('');
        }

        $this->line('   🧹 Clean up expired OTPs:');
        $this->line('      <info>./vendor/bin/directive mfa-clean</info>');
        $this->line('');
        $this->line('📖 Documentation: https://github.com/andydefer/laravel-mfa');
    }
}
