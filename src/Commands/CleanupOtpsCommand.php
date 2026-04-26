<?php

declare(strict_types=1);

namespace Kani\Otp\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Kani\Otp\Models\OneTimePassword;

/**
 * Command to clean expired and old OTPs from the database.
 *
 * This command removes:
 * - OTPs that have passed their expiration date
 * - Verified/used OTPs older than the configured retention period
 * - Cancelled OTPs older than the configured retention period
 */
final class CleanupOtpsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:cleanup 
                            {--type= : Delete OTPs of a specific type (email_verification, login, 2fa, etc.)}
                            {--days= : Delete OTPs older than X days (overrides config)} 
                            {--force : Force execution without confirmation}
                            {--keep-expired : Keep expired tokens, only clean old verified/used tokens}
                            {--dry-run : Simulate cleanup without actually deleting records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired, verified, used, and cancelled OTPs based on configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧹 Starting OTP cleanup...');
        $this->newLine();

        if (!$this->shouldProceed()) {
            $this->info('Cleanup cancelled.');

            return self::SUCCESS;
        }

        $statistics = $this->performCleanup();

        $this->displayResults($statistics);

        if ($this->option('dry-run')) {
            $this->warn('⚠️  Dry run mode - no records were actually deleted.');
        }

        return self::SUCCESS;
    }

    /**
     * Determine if the cleanup operation should proceed.
     */
    private function shouldProceed(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if ($this->option('dry-run')) {
            return true;
        }

        return $this->confirm(
            'This will permanently delete expired and old OTPs. Do you wish to continue?'
        );
    }

    /**
     * Perform the OTP cleanup operations.
     *
     * @return array<string, int> Statistics with keys: expired, verified, used, cancelled, total
     */
    private function performCleanup(): array
    {
        $statistics = [
            'expired' => 0,
            'verified' => 0,
            'used' => 0,
            'cancelled' => 0,
            'total' => 0,
        ];

        if (!$this->option('keep-expired')) {
            $statistics['expired'] = $this->deleteExpiredOtps();
        } else {
            $this->warn('Keeping expired OTPs as requested (--keep-expired)');
        }

        $statistics['verified'] = $this->deleteOldVerifiedOtps();
        $statistics['used'] = $this->deleteOldUsedOtps();
        $statistics['cancelled'] = $this->deleteOldCancelledOtps();
        $statistics['total'] = array_sum($statistics);

        return $statistics;
    }

    /**
     * Delete expired OTPs that haven't been verified, used, or cancelled.
     */
    private function deleteExpiredOtps(): int
    {
        $query = $this->buildExpiredOtpsQuery();
        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired OTPs found.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d expired OTPs (dry run)', $count));

            return $count;
        }

        $query->delete();
        $this->info(sprintf('✅ Deleted %d expired OTPs', $count));

        return $count;
    }

    /**
     * Build the query builder for expired OTPs.
     *
     * @return Builder<OneTimePassword>
     */
    private function buildExpiredOtpsQuery(): Builder
    {
        $query = OneTimePassword::query()
            ->where('expires_at', '<', now())
            ->whereNull('verified_at')
            ->whereNull('used_at')
            ->whereNull('cancelled_at');

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        return $query;
    }

    /**
     * Delete verified OTPs older than the retention period.
     */
    private function deleteOldVerifiedOtps(): int
    {
        $retentionDays = $this->getRetentionDays();

        if ($retentionDays <= 0) {
            $this->warn('Retention period is set to 0 or negative, skipping verified OTP cleanup');

            return 0;
        }

        $cutoffDate = $this->calculateCutoffDate($retentionDays);
        $query = $this->buildVerifiedOtpsQuery($cutoffDate);
        $count = $query->count();

        if ($count === 0) {
            $this->info('No old verified OTPs found.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d verified OTPs (dry run)', $count));

            return $count;
        }

        $query->delete();
        $this->info(sprintf('✅ Deleted %d verified OTPs (older than %d days)', $count, $retentionDays));

        return $count;
    }

    /**
     * Build the query builder for verified OTPs.
     *
     * @param CarbonInterface $cutoffDate Date to compare against verified_at
     *
     * @return Builder<OneTimePassword>
     */
    private function buildVerifiedOtpsQuery(CarbonInterface $cutoffDate): Builder
    {
        $query = OneTimePassword::query()
            ->where('verified_at', '<', $cutoffDate);

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        return $query;
    }

    /**
     * Delete used OTPs older than the retention period.
     */
    private function deleteOldUsedOtps(): int
    {
        $retentionDays = $this->getRetentionDays();

        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoffDate = $this->calculateCutoffDate($retentionDays);
        $query = $this->buildUsedOtpsQuery($cutoffDate);
        $count = $query->count();

        if ($count === 0) {
            $this->info('No old used OTPs found.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d used OTPs (dry run)', $count));

            return $count;
        }

        $query->delete();
        $this->info(sprintf('✅ Deleted %d used OTPs (older than %d days)', $count, $retentionDays));

        return $count;
    }

    /**
     * Build the query builder for used OTPs.
     *
     * @param CarbonInterface $cutoffDate Date to compare against used_at
     *
     * @return Builder<OneTimePassword>
     */
    private function buildUsedOtpsQuery(CarbonInterface $cutoffDate): Builder
    {
        $query = OneTimePassword::query()
            ->where('used_at', '<', $cutoffDate);

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        return $query;
    }

    /**
     * Delete cancelled OTPs older than the retention period.
     */
    private function deleteOldCancelledOtps(): int
    {
        $retentionDays = $this->getRetentionDays();

        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoffDate = $this->calculateCutoffDate($retentionDays);
        $query = $this->buildCancelledOtpsQuery($cutoffDate);
        $count = $query->count();

        if ($count === 0) {
            $this->info('No old cancelled OTPs found.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d cancelled OTPs (dry run)', $count));

            return $count;
        }

        $query->delete();
        $this->info(sprintf('✅ Deleted %d cancelled OTPs (older than %d days)', $count, $retentionDays));

        return $count;
    }

    /**
     * Build the query builder for cancelled OTPs.
     *
     * @param CarbonInterface $cutoffDate Date to compare against cancelled_at
     *
     * @return Builder<OneTimePassword>
     */
    private function buildCancelledOtpsQuery(CarbonInterface $cutoffDate): Builder
    {
        $query = OneTimePassword::query()
            ->where('cancelled_at', '<', $cutoffDate);

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        return $query;
    }

    /**
     * Get the retention period in days.
     *
     * Priority order:
     * 1. Command line option --days
     * 2. Configuration 'otp.cleanup.retention_days'
     * 3. Default value (30 days)
     */
    private function getRetentionDays(): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null) {
            $days = (int) $daysOption;
            $this->info(sprintf('Using retention period from command line: %d days', $days));

            return $days;
        }

        $configDays = config('otp.cleanup.retention_days', 30);
        $this->info(sprintf('Using retention period from config: %d days', (int) $configDays));

        return (int) $configDays;
    }

    /**
     * Calculate the cutoff date based on retention days.
     */
    private function calculateCutoffDate(int $retentionDays): CarbonInterface
    {
        return now()->subDays($retentionDays);
    }

    /**
     * Display the cleanup results in a formatted table.
     *
     * @param array<string, int> $statistics Cleanup statistics
     */
    private function displayResults(array $statistics): void
    {
        $this->newLine();
        $this->renderHeader();
        $this->renderStatisticsTable($statistics);
        $this->renderStatusMessage($statistics);
        $this->renderConfigurationSummary();
    }

    /**
     * Display the cleanup header.
     */
    private function renderHeader(): void
    {
        $this->line('═══════════════════════════════════════════════════════');
        $this->info('🧹 OTP CLEANUP COMPLETED');
        $this->line('═══════════════════════════════════════════════════════');
    }

    /**
     * Display the statistics table.
     *
     * @param array<string, int> $statistics Cleanup statistics
     */
    private function renderStatisticsTable(array $statistics): void
    {
        $rows = [];

        $this->addStatisticRowIfNeeded($rows, 'Expired OTPs deleted', $statistics['expired'], !$this->option('keep-expired'));
        $this->addStatisticRowIfNeeded($rows, 'Verified OTPs deleted', $statistics['verified'], $statistics['verified'] > 0);
        $this->addStatisticRowIfNeeded($rows, 'Used OTPs deleted', $statistics['used'], $statistics['used'] > 0);
        $this->addStatisticRowIfNeeded($rows, 'Cancelled OTPs deleted', $statistics['cancelled'], $statistics['cancelled'] > 0);

        if (count($rows) > 0) {
            $rows[] = ['━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━'];
            $rows[] = ['Total OTPs deleted', $statistics['total']];
        } else {
            $rows[] = ['No OTPs to delete', 0];
        }

        $this->table(['Metric', 'Count'], $rows);
        $this->newLine();
    }

    /**
     * Add a statistic row to the table if the condition is met.
     *
     * @param array<int, array<int, string>> $rows      Reference to rows array
     * @param string                         $label     Row label
     * @param int                            $value     Statistic value
     * @param bool                           $condition Condition to add row
     */
    private function addStatisticRowIfNeeded(array &$rows, string $label, int $value, bool $condition): void
    {
        if ($condition) {
            $rows[] = [$label, (string) $value];
        }
    }

    /**
     * Display the status message based on cleanup results.
     *
     * @param array<string, int> $statistics Cleanup statistics
     */
    private function renderStatusMessage(array $statistics): void
    {
        if ($statistics['total'] === 0) {
            $this->info('✨ No OTPs needed cleaning. Database is clean!');

            return;
        }

        if (!$this->option('dry-run')) {
            $this->info('✅ Cleanup completed successfully!');
        } else {
            $this->info('✅ Dry run completed successfully!');
        }
    }

    /**
     * Display the current configuration summary.
     */
    private function renderConfigurationSummary(): void
    {
        $this->newLine();
        $this->line('📋 Current Configuration:');
        $this->line(sprintf('   • Retention period: %d days', $this->getRetentionDays()));

        if ($type = $this->option('type')) {
            $this->line(sprintf('   • Filtered by type: %s', $type));
        }

        $this->renderExpiredTokensStatus();
    }

    /**
     * Display the status of expired token handling.
     */
    private function renderExpiredTokensStatus(): void
    {
        if (!$this->option('keep-expired')) {
            $this->line('   • Expired OTPs: ✅ Removed');
        } else {
            $this->line('   • Expired OTPs: ⏸️  Kept (--keep-expired flag used)');
        }
    }
}
