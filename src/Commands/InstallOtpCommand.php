<?php

declare(strict_types=1);

namespace Kani\Otp\Commands;

use Illuminate\Console\Command;
use Kani\Otp\Services\OtpInstallerService;

/**
 * Command to install the Laravel OTP package.
 *
 * Publishes configuration files, migrations, and optionally runs migrations
 * to set up the package for first-time use.
 */
final class InstallOtpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:install 
                            {--force : Force publish without confirmation}
                            {--no-migrate : Skip database migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Laravel OTP package for one-time password management';

    /**
     * Execute the console command.
     */
    public function handle(OtpInstallerService $installer): int
    {
        $installer->install(
            command: $this,
            force: (bool) $this->option('force'),
            skipMigrations: (bool) $this->option('no-migrate')
        );

        return self::SUCCESS;
    }
}
