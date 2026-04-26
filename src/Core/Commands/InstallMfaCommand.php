<?php

declare(strict_types=1);

namespace Kani\Mfa\Core\Commands;

use Illuminate\Console\Command;
use Kani\Mfa\Core\Services\MfaInstallerService;

/**
 * Console command for installing the Laravel MFA package.
 *
 * By default installs both OTP and TOTP (2FA) systems.
 * Users can choose to skip specific components using the available options.
 */
final class InstallMfaCommand extends Command
{
    /**
     * The console command signature and available options.
     *
     * @var string
     */
    protected $signature = 'mfa:install 
                            {--force : Force publishing without confirmation prompt}
                            {--no-migrate : Skip database migrations after publishing}
                            {--without-otp : Skip OTP (email/sms one-time passwords) installation}
                            {--without-totp : Skip TOTP (Google Authenticator 2FA) installation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Laravel MFA package for multi-factor authentication management (OTP + TOTP)';

    /**
     * Execute the console command to install the package.
     *
     * @param  MfaInstallerService  $installer  Service that handles the installation logic
     * @return int Command exit code (0 for success)
     */
    public function handle(MfaInstallerService $installer): int
    {
        $installer->install(
            command: $this,
            force: (bool) $this->option('force'),
            skipMigrations: (bool) $this->option('no-migrate'),
            includeOtp: ! (bool) $this->option('without-otp'),
            includeTotp: ! (bool) $this->option('without-totp')
        );

        return self::SUCCESS;
    }
}
