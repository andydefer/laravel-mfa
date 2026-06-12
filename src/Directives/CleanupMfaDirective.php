<?php

// src/Directives/CleanupMfaDirective.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\Mfa\Otp\Models\OneTimePassword;
use AndyDefer\Mfa\Totp\Models\TwoFactorSecret;
use Carbon\CarbonInterface;

final class CleanupMfaDirective extends AbstractDirective
{
    public function __construct(
        DirectiveContext $context,
        DirectiveInteractionService $interaction,
    ) {
        parent::__construct($context, $interaction);
    }

    public function getSignature(): string
    {
        return 'mfa-cleanup 
                {--type= : Delete OTPs of a specific type}
                {--days= : Delete OTPs and 2FA secrets older than X days} 
                {--force : Force execution without confirmation}
                {--keep-expired : Keep expired tokens, only clean old verified/used tokens}
                {--dry-run : Simulate cleanup without actually deleting records}
                {--otp-only : Only clean OTP records, skip 2FA secrets}
                {--totp-only : Only clean TOTP/2FA secrets, skip OTP records}';
    }

    public function getDescription(): string
    {
        return 'Clean expired OTPs and old two-factor authentication secrets from the database';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('mfa-clean');
        $aliases->add('mfa-purge');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        $this->info('🧹 Starting MFA cleanup...');
        $this->newLine();

        if (! $this->shouldProceed()) {
            $this->info('Cleanup cancelled.');

            return ExitCode::SUCCESS;
        }

        $statistics = $this->performCleanup();
        $this->displayResults($statistics);

        if ($this->hasOption('dry-run')) {
            $this->warn('⚠️  Dry run mode - no records were actually deleted.');
        }

        return ExitCode::SUCCESS;
    }

    private function shouldProceed(): bool
    {
        if ($this->hasOption('force') || $this->hasOption('dry-run')) {
            return true;
        }

        return $this->confirm('This will permanently delete expired OTPs and old 2FA secrets. Do you wish to continue?');
    }

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

        if (! $this->hasOption('totp-only')) {
            $otpStats = $this->cleanupOtpRecords();
            $statistics['expired'] = $otpStats['expired'];
            $statistics['verified'] = $otpStats['verified'];
            $statistics['used'] = $otpStats['used'];
            $statistics['cancelled'] = $otpStats['cancelled'];
        }

        if (! $this->hasOption('otp-only')) {
            $totpStats = $this->cleanupTotpRecords();
            $statistics['totp_disabled'] = $totpStats['totp_disabled'];
            $statistics['totp_expired'] = $totpStats['totp_expired'];
        }

        $statistics['total'] = $statistics['expired'] + $statistics['verified'] +
            $statistics['used'] + $statistics['cancelled'] +
            $statistics['totp_disabled'] + $statistics['totp_expired'];

        return $statistics;
    }

    private function cleanupOtpRecords(): array
    {
        $stats = ['expired' => 0, 'verified' => 0, 'used' => 0, 'cancelled' => 0];

        if (! $this->hasOption('keep-expired')) {
            $stats['expired'] = $this->deleteExpiredOtps();
        } else {
            $this->warn('Keeping expired OTPs as requested (--keep-expired)');
        }

        $stats['verified'] = $this->deleteOldVerifiedOtps();
        $stats['used'] = $this->deleteOldUsedOtps();
        $stats['cancelled'] = $this->deleteOldCancelledOtps();

        return $stats;
    }

    private function cleanupTotpRecords(): array
    {
        $retentionDays = $this->getRetentionDays();
        $stats = ['totp_disabled' => 0, 'totp_expired' => 0];

        if ($retentionDays <= 0) {
            $this->warn('Retention period is set to 0 or negative, skipping 2FA secret cleanup');

            return $stats;
        }

        $cutoffDate = $this->calculateCutoffDate($retentionDays);
        $stats['totp_disabled'] = $this->deleteOldDisabledSecrets($cutoffDate);
        $stats['totp_expired'] = $this->deleteOldConfirmedSecrets($cutoffDate);

        return $stats;
    }

    private function deleteExpiredOtps(): int
    {
        $query = OneTimePassword::query()
            ->where('expires_at', '<', now())
            ->whereNull('verified_at')
            ->whereNull('used_at')
            ->whereNull('cancelled_at');

        $type = $this->option('type');
        if ($type) {
            $query->where('type', $type);
        }

        $count = $query->count();
        if ($count === 0) {
            return 0;
        }

        if ($this->hasOption('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d expired OTPs (dry run)', $count));

            return $count;
        }

        $query->delete();
        $this->info(sprintf('✅ Deleted %d expired OTPs', $count));

        return $count;
    }

    private function deleteOldVerifiedOtps(): int
    {
        $retentionDays = $this->getRetentionDays();
        if ($retentionDays <= 0) {
            return 0;
        }

        $query = OneTimePassword::query()
            ->where('verified_at', '<', $this->calculateCutoffDate($retentionDays));

        $type = $this->option('type');
        if ($type) {
            $query->where('type', $type);
        }

        $count = $query->count();
        if ($count === 0) {
            return 0;
        }

        if ($this->hasOption('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d verified OTPs (dry run)', $count));

            return $count;
        }

        $query->delete();
        $this->info(sprintf('✅ Deleted %d verified OTPs (older than %d days)', $count, $retentionDays));

        return $count;
    }

    private function deleteOldUsedOtps(): int
    {
        $retentionDays = $this->getRetentionDays();
        if ($retentionDays <= 0) {
            return 0;
        }

        $query = OneTimePassword::query()
            ->where('used_at', '<', $this->calculateCutoffDate($retentionDays));

        $type = $this->option('type');
        if ($type) {
            $query->where('type', $type);
        }

        $count = $query->count();
        if ($count === 0) {
            return 0;
        }

        if ($this->hasOption('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d used OTPs (dry run)', $count));

            return $count;
        }

        $query->delete();
        $this->info(sprintf('✅ Deleted %d used OTPs (older than %d days)', $count, $retentionDays));

        return $count;
    }

    private function deleteOldCancelledOtps(): int
    {
        $retentionDays = $this->getRetentionDays();
        if ($retentionDays <= 0) {
            return 0;
        }

        $query = OneTimePassword::query()
            ->where('cancelled_at', '<', $this->calculateCutoffDate($retentionDays));

        $type = $this->option('type');
        if ($type) {
            $query->where('type', $type);
        }

        $count = $query->count();
        if ($count === 0) {
            return 0;
        }

        if ($this->hasOption('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d cancelled OTPs (dry run)', $count));

            return $count;
        }

        $query->delete();
        $this->info(sprintf('✅ Deleted %d cancelled OTPs (older than %d days)', $count, $retentionDays));

        return $count;
    }

    private function deleteOldDisabledSecrets(CarbonInterface $cutoffDate): int
    {
        $query = TwoFactorSecret::query()
            ->where('is_enabled', false)
            ->where(function ($q) use ($cutoffDate) {
                $q->where('updated_at', '<', $cutoffDate)
                    ->orWhere('created_at', '<', $cutoffDate);
            });

        $count = $query->count();
        if ($count === 0) {
            return 0;
        }

        if ($this->hasOption('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d disabled 2FA secrets (dry run)', $count));

            return $count;
        }

        $deleted = $query->delete();
        $this->info(sprintf('✅ Deleted %d disabled 2FA secrets (older than %d days)', $deleted, $this->getRetentionDays()));

        return $deleted;
    }

    private function deleteOldConfirmedSecrets(CarbonInterface $cutoffDate): int
    {
        $query = TwoFactorSecret::query()
            ->where('is_enabled', true)
            ->where('confirmed_at', '<', $cutoffDate)
            ->where(function ($q) use ($cutoffDate) {
                $q->where('last_used_at', '<', $cutoffDate)
                    ->orWhereNull('last_used_at');
            });

        $count = $query->count();
        if ($count === 0) {
            return 0;
        }

        if ($this->hasOption('dry-run')) {
            $this->info(sprintf('🔍 Would delete %d old unused 2FA secrets (dry run)', $count));

            return $count;
        }

        $deleted = $query->delete();
        $this->info(sprintf('✅ Deleted %d old unused 2FA secrets (older than %d days)', $deleted, $this->getRetentionDays()));

        return $deleted;
    }

    private function getRetentionDays(): int
    {
        $days = $this->option('days');
        if ($days && $days !== '') {
            return (int) $days;
        }
        $configDays = (int) config('mfa.cleanup.retention_days', 30);

        return $configDays;
    }

    private function calculateCutoffDate(int $retentionDays): CarbonInterface
    {
        return now()->subDays($retentionDays);
    }

    private function displayResults(array $statistics): void
    {
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════');
        $this->info('🧹 MFA CLEANUP COMPLETED');
        $this->line('═══════════════════════════════════════════════════════');

        $this->renderStatisticsTable($statistics);

        if ($statistics['total'] === 0) {
            $this->info('✨ No MFA records needed cleaning. Database is clean!');
        } elseif (! $this->hasOption('dry-run')) {
            $this->info('✅ Cleanup completed successfully!');
        } else {
            $this->info('✅ Dry run completed successfully!');
        }

        $this->renderConfigurationSummary();
    }

    private function renderStatisticsTable(array $statistics): void
    {
        $headers = new StringTypedCollection;
        $headers->add('Metric', 'Count');

        $rows = new RowCollection;

        if (! $this->hasOption('keep-expired') && ! $this->hasOption('totp-only') && $statistics['expired'] > 0) {
            $row = new RowCollection;
            $row->add('Expired OTPs deleted', (string) $statistics['expired']);
            $rows->add($row);
        }

        if (! $this->hasOption('totp-only') && $statistics['verified'] > 0) {
            $row = new RowCollection;
            $row->add('Verified OTPs deleted', (string) $statistics['verified']);
            $rows->add($row);
        }

        if (! $this->hasOption('totp-only') && $statistics['used'] > 0) {
            $row = new RowCollection;
            $row->add('Used OTPs deleted', (string) $statistics['used']);
            $rows->add($row);
        }

        if (! $this->hasOption('totp-only') && $statistics['cancelled'] > 0) {
            $row = new RowCollection;
            $row->add('Cancelled OTPs deleted', (string) $statistics['cancelled']);
            $rows->add($row);
        }

        if (! $this->hasOption('otp-only') && $statistics['totp_disabled'] > 0) {
            $row = new RowCollection;
            $row->add('Disabled 2FA secrets deleted', (string) $statistics['totp_disabled']);
            $rows->add($row);
        }

        if (! $this->hasOption('otp-only') && $statistics['totp_expired'] > 0) {
            $row = new RowCollection;
            $row->add('Unused 2FA secrets deleted', (string) $statistics['totp_expired']);
            $rows->add($row);
        }

        if ($rows->isNotEmpty()) {
            $sepRow = new RowCollection;
            $sepRow->add('━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━');
            $rows->add($sepRow);

            $totalRow = new RowCollection;
            $totalRow->add('Total records deleted', (string) $statistics['total']);
            $rows->add($totalRow);
        } else {
            $noRow = new RowCollection;
            $noRow->add('No records to delete', '0');
            $rows->add($noRow);
        }

        $this->table($headers, $rows);
        $this->newLine();
    }

    private function renderConfigurationSummary(): void
    {
        $this->newLine();
        $this->line('📋 Current Configuration:');
        $this->line(sprintf('   • Retention period: %d days', $this->getRetentionDays()));

        $type = $this->option('type');
        if ($type) {
            $this->line(sprintf('   • Filtered OTPs by type: %s', $type));
        }

        if ($this->hasOption('otp-only')) {
            $this->line('   • Mode: OTP only (2FA secrets excluded)');
        }
        if ($this->hasOption('totp-only')) {
            $this->line('   • Mode: TOTP/2FA only (OTPs excluded)');
        }

        if (! $this->hasOption('keep-expired')) {
            $this->line('   • Expired OTPs: ✅ Removed');
        } else {
            $this->line('   • Expired OTPs: ⏸️  Kept (--keep-expired flag used)');
        }
    }
}
