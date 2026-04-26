<?php

declare(strict_types=1);

namespace Kani\Mfa\Core\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Kani\Mfa\Otp\Models\OneTimePassword;
use Kani\Mfa\Totp\Models\TwoFactorSecret;

/**
 * Command to clean expired and old MFA data from the database.
 *
 * This command removes:
 * - OTPs that have passed their expiration date
 * - Verified/used/cancelled OTPs older than the configured retention period
 * - Disabled/expired two-factor secrets older than the configured retention period
 *
 * @package Kani\Mfa\Commands
 */
final class CleanupMfaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mfa:cleanup 
                            {--type= : Delete OTPs of a specific type (email_verification, login, 2fa, etc.)}
                            {--days= : Delete OTPs and 2FA secrets older than X days (overrides config)} 
                            {--force : Force execution without confirmation}
                            {--keep-expired : Keep expired tokens, only clean old verified/used tokens}
                            {--dry-run : Simulate cleanup without actually deleting records}
                            {--otp-only : Only clean OTP records, skip 2FA secrets}
                            {--totp-only : Only clean TOTP/2FA secrets, skip OTP records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired OTPs and old two-factor authentication secrets from the database';

    /**
     * Execute the console command.
     *
     * @return int Exit code (0 for success)
     */
    public function handle(): int
    {
        $this->info('🧹 Starting MFA cleanup...');
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
     *
     * @return bool True if cleanup should proceed, false otherwise
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
            'This will permanently delete expired OTPs and old 2FA secrets. Do you wish to continue?'
        );
    }

    /**
     * Perform the MFA cleanup operations.
     *
     * @return array<string, int> Statistics with keys: 
     *                            - expired: Number of expired OTPs deleted
     *                            - verified: Number of verified OTPs deleted
     *                            - used: Number of used OTPs deleted
     *                            - cancelled: Number of cancelled OTPs deleted
     *                            - totp_disabled: Number of disabled 2FA secrets deleted
     *                            - totp_expired: Number of expired 2FA secrets deleted
     *                            - total: Total number of records deleted
     */
    private function performCleanup(): array
    {
        $statistics = [
            'expired' => 0,
            'verified' => 0,
            'used' => 0,
            'cancelled' => 0,
            'totp_disabled' => 0,
            'totp_expired' => 0,
            'total' => 0,
        ];

        if (!$this->option('totp-only')) {
            $otpStatistics = $this->cleanupOtpRecords();
            $statistics['expired'] = $otpStatistics['expired'];
            $statistics['verified'] = $otpStatistics['verified'];
            $statistics['used'] = $otpStatistics['used'];
            $statistics['cancelled'] = $otpStatistics['cancelled'];
        }

        if (!$this->option('otp-only')) {
            $totpStatistics = $this->cleanupTotpRecords();
            $statistics['totp_disabled'] = $totpStatistics['totp_disabled'];
            $statistics['totp_expired'] = $totpStatistics['totp_expired'];
        }

        $statistics['total'] = $statistics['expired'] + $statistics['verified'] +
            $statistics['used'] + $statistics['cancelled'] +
            $statistics['totp_disabled'] + $statistics['totp_expired'];

        return $statistics;
    }

    /**
     * Clean up OTP records based on expiration and retention policies.
     *
     * @return array<string, int> Statistics for OTP deletions
     */
    private function cleanupOtpRecords(): array
    {
        $statistics = [
            'expired' => 0,
            'verified' => 0,
            'used' => 0,
            'cancelled' => 0,
        ];

        if (!$this->option('keep-expired')) {
            $statistics['expired'] = $this->deleteExpiredOtps();
        } else {
            $this->warn('Keeping expired OTPs as requested (--keep-expired)');
        }

        $statistics['verified'] = $this->deleteOldVerifiedOtps();
        $statistics['used'] = $this->deleteOldUsedOtps();
        $statistics['cancelled'] = $this->deleteOldCancelledOtps();

        return $statistics;
    }

    /**
     * Clean up TOTP/2FA records based on retention period.
     *
     * @return array<string, int> Statistics for TOTP/2FA deletions
     */
    private function cleanupTotpRecords(): array
    {
        $retentionDays = $this->getRetentionDays();
        $statistics = [
            'totp_disabled' => 0,
            'totp_expired' => 0,
        ];

        if ($retentionDays <= 0) {
            $this->warn('Retention period is set to 0 or negative, skipping 2FA secret cleanup');

            return $statistics;
        }

        $cutoffDate = $this->calculateCutoffDate($retentionDays);

        $statistics['totp_disabled'] = $this->deleteOldDisabledSecrets($cutoffDate);
        $statistics['totp_expired'] = $this->deleteOldConfirmedSecrets($cutoffDate);

        return $statistics;
    }

    /**
     * Delete expired OTPs that haven't been verified, used, or cancelled.
     *
     * @return int Number of deleted expired OTPs
     */
    private function deleteExpiredOtps(): int
    {
        $query = $this->buildExpiredOtpsQuery();
        $count = $query->count();

        if ($count === 0) {
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

        $typeFilter = $this->option('type');
        if ($typeFilter !== null) {
            $query->where('type', $typeFilter);
        }

        return $query;
    }

    /**
     * Delete verified OTPs older than the retention period.
     *
     * @return int Number of deleted verified OTPs
     */
    private function deleteOldVerifiedOtps(): int
    {
        $retentionDays = $this->getRetentionDays();

        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoffDate = $this->calculateCutoffDate($retentionDays);
        $query = $this->buildVerifiedOtpsQuery($cutoffDate);
        $count = $query->count();

        if ($count === 0) {
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
     * Build the query builder for verified OTPs older than cutoff date.
     *
     * @param CarbonInterface $cutoffDate Date to compare against verified_at
     *
     * @return Builder<OneTimePassword>
     */
    private function buildVerifiedOtpsQuery(CarbonInterface $cutoffDate): Builder
    {
        $query = OneTimePassword::query()
            ->where('verified_at', '<', $cutoffDate);

        $typeFilter = $this->option('type');
        if ($typeFilter !== null) {
            $query->where('type', $typeFilter);
        }

        return $query;
    }

    /**
     * Delete used OTPs older than the retention period.
     *
     * @return int Number of deleted used OTPs
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
     * Build the query builder for used OTPs older than cutoff date.
     *
     * @param CarbonInterface $cutoffDate Date to compare against used_at
     *
     * @return Builder<OneTimePassword>
     */
    private function buildUsedOtpsQuery(CarbonInterface $cutoffDate): Builder
    {
        $query = OneTimePassword::query()
            ->where('used_at', '<', $cutoffDate);

        $typeFilter = $this->option('type');
        if ($typeFilter !== null) {
            $query->where('type', $typeFilter);
        }

        return $query;
    }

    /**
     * Delete cancelled OTPs older than the retention period.
     *
     * @return int Number of deleted cancelled OTPs
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
     * Build the query builder for cancelled OTPs older than cutoff date.
     *
     * @param CarbonInterface $cutoffDate Date to compare against cancelled_at
     *
     * @return Builder<OneTimePassword>
     */
    private function buildCancelledOtpsQuery(CarbonInterface $cutoffDate): Builder
    {
        $query = OneTimePassword::query()
            ->where('cancelled_at', '<', $cutoffDate);

        $typeFilter = $this->option('type');
        if ($typeFilter !== null) {
            $query->where('type', $typeFilter);
        }

        return $query;
    }

    /**
     * Delete disabled 2FA secrets older than the retention period.
     *
     * @param CarbonInterface $cutoffDate Date threshold for deletion
     *
     * @return int Number of deleted disabled secrets
     */
    private function deleteOldDisabledSecrets(CarbonInterface $cutoffDate): int
    {
        $query = TwoFactorSecret::query()
            ->where('is_enabled', false)
            ->where(function ($queryBuilder) use ($cutoffDate): void {
                $queryBuilder->where('updated_at', '<', $cutoffDate)
                    ->orWhere('created_at', '<', $cutoffDate);
            });

        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d disabled 2FA secrets (dry run)', $count));

            return $count;
        }

        $deletedCount = $query->delete();
        $this->info(sprintf('✅ Deleted %d disabled 2FA secrets (older than %d days)', $deletedCount, $this->getRetentionDays()));

        return $deletedCount;
    }

    /**
     * Delete old confirmed/expired 2FA secrets that haven't been used.
     *
     * @param CarbonInterface $cutoffDate Date threshold for deletion
     *
     * @return int Number of deleted old confirmed secrets
     */
    private function deleteOldConfirmedSecrets(CarbonInterface $cutoffDate): int
    {
        $query = TwoFactorSecret::query()
            ->where('is_enabled', true)
            ->where('confirmed_at', '<', $cutoffDate)
            ->where(function ($queryBuilder) use ($cutoffDate): void {
                $queryBuilder->where('last_used_at', '<', $cutoffDate)
                    ->orWhereNull('last_used_at');
            });

        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d old unused 2FA secrets (dry run)', $count));

            return $count;
        }

        $deletedCount = $query->delete();
        $this->info(sprintf('✅ Deleted %d old unused 2FA secrets (older than %d days)', $deletedCount, $this->getRetentionDays()));

        return $deletedCount;
    }

    /**
     * Get the retention period in days.
     *
     * Priority order:
     * 1. Command line option --days
     * 2. Configuration 'mfa.cleanup.retention_days'
     * 3. Default value (30 days)
     *
     * @return int Retention period in days
     */
    private function getRetentionDays(): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null) {
            return (int) $daysOption;
        }

        return (int) config('mfa.cleanup.retention_days', 30);
    }

    /**
     * Calculate the cutoff date based on retention days.
     *
     * @param int $retentionDays Number of days to subtract from current date
     *
     * @return CarbonInterface Date threshold for deletion
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
        $this->info('🧹 MFA CLEANUP COMPLETED');
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

        $this->addStatisticRowIfNeeded($rows, 'Expired OTPs deleted', $statistics['expired'], !$this->option('keep-expired') && !$this->option('totp-only'));
        $this->addStatisticRowIfNeeded($rows, 'Verified OTPs deleted', $statistics['verified'], $statistics['verified'] > 0 && !$this->option('totp-only'));
        $this->addStatisticRowIfNeeded($rows, 'Used OTPs deleted', $statistics['used'], $statistics['used'] > 0 && !$this->option('totp-only'));
        $this->addStatisticRowIfNeeded($rows, 'Cancelled OTPs deleted', $statistics['cancelled'], $statistics['cancelled'] > 0 && !$this->option('totp-only'));
        $this->addStatisticRowIfNeeded($rows, 'Disabled 2FA secrets deleted', $statistics['totp_disabled'], $statistics['totp_disabled'] > 0 && !$this->option('otp-only'));
        $this->addStatisticRowIfNeeded($rows, 'Unused 2FA secrets deleted', $statistics['totp_expired'], $statistics['totp_expired'] > 0 && !$this->option('otp-only'));

        if (count($rows) > 0) {
            $rows[] = ['━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━'];
            $rows[] = ['Total records deleted', $statistics['total']];
        } else {
            $rows[] = ['No records to delete', 0];
        }

        $this->table(['Metric', 'Count'], $rows);
        $this->newLine();
    }

    /**
     * Add a statistic row to the table if the condition is met.
     *
     * @param array<int, array<int, string>> $rows Reference to rows array
     * @param string $label Row label
     * @param int $value Statistic value
     * @param bool $condition Condition to add row
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
            $this->info('✨ No MFA records needed cleaning. Database is clean!');

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

        $typeFilter = $this->option('type');
        if ($typeFilter !== null) {
            $this->line(sprintf('   • Filtered OTPs by type: %s', $typeFilter));
        }

        if ($this->option('otp-only')) {
            $this->line('   • Mode: OTP only (2FA secrets excluded)');
        }

        if ($this->option('totp-only')) {
            $this->line('   • Mode: TOTP/2FA only (OTPs excluded)');
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
