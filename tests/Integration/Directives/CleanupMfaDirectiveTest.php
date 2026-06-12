<?php

// tests/Integration/Directives/CleanupMfaDirectiveTest.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Mfa\Directives\CleanupMfaDirective;
use AndyDefer\Mfa\Otp\Models\OneTimePassword;
use AndyDefer\Mfa\Tests\Fixtures\Models\TestUser;
use AndyDefer\Mfa\Tests\IntegrationTestCase;
use AndyDefer\Mfa\Totp\Models\TwoFactorSecret;
use Carbon\Carbon;

final class CleanupMfaDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));
        $this->service = new DirectiveTestingService($this->app);

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->service->destroy();
        parent::tearDown();
    }

    // ============================================================================
    // OTP Helper Methods
    // ============================================================================

    private function createExpiredOtp(): OneTimePassword
    {
        $otp = OneTimePassword::create([
            'otpable_type' => $this->user->getMorphClass(),
            'otpable_id' => $this->user->id,
            'type' => 'email_verification',
            'token_hash' => hash('sha256', '123456'),
            'destination' => 'user@example.com',
            'expires_at' => Carbon::getTestNow()->subMinute(),
            'max_attempts' => 3,
            'attempts' => 0,
        ]);

        return $otp;
    }

    private function createValidOtp(): OneTimePassword
    {
        $otp = OneTimePassword::create([
            'otpable_type' => $this->user->getMorphClass(),
            'otpable_id' => $this->user->id,
            'type' => 'email_verification',
            'token_hash' => hash('sha256', '123456'),
            'destination' => 'user@example.com',
            'expires_at' => Carbon::getTestNow()->addHour(),
            'max_attempts' => 3,
            'attempts' => 0,
        ]);

        return $otp;
    }

    private function createVerifiedOtp(int $daysOld = 40): OneTimePassword
    {
        $date = Carbon::getTestNow()->subDays($daysOld);
        $otp = OneTimePassword::create([
            'otpable_type' => $this->user->getMorphClass(),
            'otpable_id' => $this->user->id,
            'type' => 'email_verification',
            'token_hash' => hash('sha256', '123456'),
            'destination' => 'user@example.com',
            'expires_at' => Carbon::getTestNow()->addHour(),
            'verified_at' => $date,
            'max_attempts' => 3,
            'attempts' => 0,
        ]);

        return $otp;
    }

    private function createUsedOtp(): OneTimePassword
    {
        $date = Carbon::getTestNow()->subDays(40);
        $otp = OneTimePassword::create([
            'otpable_type' => $this->user->getMorphClass(),
            'otpable_id' => $this->user->id,
            'type' => 'email_verification',
            'token_hash' => hash('sha256', '123456'),
            'destination' => 'user@example.com',
            'expires_at' => Carbon::getTestNow()->addHour(),
            'used_at' => $date,
            'max_attempts' => 3,
            'attempts' => 0,
        ]);

        return $otp;
    }

    private function createCancelledOtp(): OneTimePassword
    {
        $date = Carbon::getTestNow()->subDays(40);
        $otp = OneTimePassword::create([
            'otpable_type' => $this->user->getMorphClass(),
            'otpable_id' => $this->user->id,
            'type' => 'email_verification',
            'token_hash' => hash('sha256', '123456'),
            'destination' => 'user@example.com',
            'expires_at' => Carbon::getTestNow()->addHour(),
            'cancelled_at' => $date,
            'max_attempts' => 3,
            'attempts' => 0,
        ]);

        return $otp;
    }

    // ============================================================================
    // TOTP Helper Methods
    // ============================================================================

    private function createDisabledTotpSecret(int $daysOld = 40): TwoFactorSecret
    {
        $date = Carbon::getTestNow()->subDays($daysOld);

        $secret = TwoFactorSecret::create([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->id,
            'secret' => 'JBSWY3DPEHPK3PXP',
            'is_enabled' => false,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        return $secret;
    }

    private function createExpiredTotpSecret(): TwoFactorSecret
    {
        $date = Carbon::getTestNow()->subDays(40);
        $secret = TwoFactorSecret::create([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->id,
            'secret' => 'JBSWY3DPEHPK3PXP',
            'is_enabled' => true,
            'confirmed_at' => $date,
            'last_used_at' => Carbon::getTestNow()->subDays(35),
        ]);

        return $secret;
    }

    private function createActiveTotpSecret(): TwoFactorSecret
    {
        $secret = TwoFactorSecret::create([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->id,
            'secret' => 'JBSWY3DPEHPK3PXP',
            'is_enabled' => true,
            'confirmed_at' => Carbon::getTestNow()->subDays(5),
            'last_used_at' => Carbon::getTestNow()->subDay(),
        ]);

        return $secret;
    }

    // ============================================================================
    // Signature, Description & Aliases Tests
    // ============================================================================

    public function test_get_signature_returns_mfa_cleanup(): void
    {
        $directive = $this->app->make(CleanupMfaDirective::class);
        $signature = $directive->getSignature();
        $this->assertStringContainsString('mfa-cleanup', $signature);
    }

    public function test_get_description_returns_string(): void
    {
        $directive = $this->app->make(CleanupMfaDirective::class);
        $description = $directive->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->app->make(CleanupMfaDirective::class);
        $aliases = $directive->getAliases();
        $this->assertTrue($aliases->contains('mfa-clean'));
        $this->assertTrue($aliases->contains('mfa-purge'));
        $this->assertSame(2, $aliases->count());
    }

    // ============================================================================
    // OTP Cleanup Tests
    // ============================================================================

    public function test_execute_deletes_expired_otps(): void
    {
        $this->createExpiredOtp();
        $this->createValidOtp();

        $response = $this->service->run(CleanupMfaDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 expired OTPs', $response->output);
        $this->assertCount(1, OneTimePassword::all());
    }

    public function test_execute_keeps_expired_otps_when_keep_expired_flag(): void
    {
        $this->createExpiredOtp();
        $this->createValidOtp();

        $response = $this->service->run(CleanupMfaDirective::class, ['--force', '--keep-expired']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Keeping expired OTPs', $response->output);
        $this->assertCount(2, OneTimePassword::all());
    }

    public function test_execute_deletes_old_verified_otps(): void
    {
        $this->createVerifiedOtp(40);
        $this->createValidOtp();

        $response = $this->service->run(CleanupMfaDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 verified OTPs', $response->output);
        $this->assertCount(1, OneTimePassword::all());
    }

    public function test_execute_deletes_old_used_otps(): void
    {
        $this->createUsedOtp();
        $this->createValidOtp();

        $response = $this->service->run(CleanupMfaDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 used OTPs', $response->output);
        $this->assertCount(1, OneTimePassword::all());
    }

    public function test_execute_deletes_old_cancelled_otps(): void
    {
        $this->createCancelledOtp();
        $this->createValidOtp();

        $response = $this->service->run(CleanupMfaDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 cancelled OTPs', $response->output);
        $this->assertCount(1, OneTimePassword::all());
    }

    // ============================================================================
    // TOTP Cleanup Tests
    // ============================================================================

    public function test_execute_deletes_disabled_totp_secrets(): void
    {
        $this->createDisabledTotpSecret(40);

        $response = $this->service->run(CleanupMfaDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 disabled 2FA secrets', $response->output);
        $this->assertCount(0, TwoFactorSecret::all());
    }

    public function test_execute_keeps_recent_disabled_totp_secrets(): void
    {
        $this->createDisabledTotpSecret(5);

        $response = $this->service->run(CleanupMfaDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No records to delete', $response->output);
        $this->assertCount(1, TwoFactorSecret::all());
    }

    public function test_execute_deletes_expired_totp_secrets(): void
    {
        $this->createExpiredTotpSecret();
        $this->createActiveTotpSecret();

        $response = $this->service->run(CleanupMfaDirective::class, ['--force']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 old unused 2FA secrets', $response->output);
        $this->assertCount(1, TwoFactorSecret::all());
    }

    // ============================================================================
    // Filter Mode Tests
    // ============================================================================

    public function test_execute_otp_only_skips_totp(): void
    {
        $this->createExpiredOtp();
        $this->createDisabledTotpSecret(40);

        $response = $this->service->run(CleanupMfaDirective::class, ['--force', '--otp-only']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 expired OTPs', $response->output);

        // ✅ On vérifie que le TOTP n'a PAS été supprimé (reste en base)
        // Pas besoin de vérifier l'absence du message "2FA secrets" car c'est implicite
        $this->assertCount(1, TwoFactorSecret::all());
        $this->assertCount(0, OneTimePassword::all());
    }

    public function test_execute_totp_only_skips_otp(): void
    {
        $this->createExpiredOtp();
        $this->createDisabledTotpSecret(40);

        $response = $this->service->run(CleanupMfaDirective::class, ['--force', '--totp-only']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 disabled 2FA secrets', $response->output);

        // ✅ On vérifie que l'OTP n'a PAS été supprimé (reste en base)
        $this->assertCount(1, OneTimePassword::all());
        $this->assertCount(0, TwoFactorSecret::all());
    }

    // ============================================================================
    // Dry Run Tests
    // ============================================================================

    public function test_execute_dry_run_does_not_delete_records(): void
    {
        $this->createExpiredOtp();
        $this->createVerifiedOtp(40);

        $response = $this->service->run(CleanupMfaDirective::class, ['--force', '--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would delete 1 expired OTPs', $response->output);
        $this->assertStringContainsString('Would delete 1 verified OTPs', $response->output);
        $this->assertCount(2, OneTimePassword::all());
    }

    // ============================================================================
    // Display Tests
    // ============================================================================

    public function test_execute_displays_statistics_table(): void
    {
        $this->createExpiredOtp();
        $this->createVerifiedOtp(40);

        $response = $this->service->run(CleanupMfaDirective::class, ['--force']);

        $this->assertStringContainsString('Expired OTPs deleted', $response->output);
        $this->assertStringContainsString('Verified OTPs deleted', $response->output);
        $this->assertStringContainsString('Total records deleted', $response->output);
    }

    public function test_execute_displays_no_records_message(): void
    {
        $response = $this->service->run(CleanupMfaDirective::class, ['--force']);

        $this->assertStringContainsString('No MFA records needed cleaning', $response->output);
    }

    // ============================================================================
    // Custom Retention Days Tests
    // ============================================================================

    public function test_execute_uses_custom_retention_days(): void
    {
        $recentOtp = $this->createVerifiedOtp(1);

        $response = $this->service->run(CleanupMfaDirective::class, ['--force', '--days=2']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No records to delete', $response->output);
        $this->assertCount(1, OneTimePassword::all());
    }

    public function test_execute_custom_retention_deletes_old_records(): void
    {
        $oldOtp = $this->createVerifiedOtp(3);

        $response = $this->service->run(CleanupMfaDirective::class, ['--force', '--days=2']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 verified OTPs', $response->output);
        $this->assertCount(0, OneTimePassword::all());
    }

    // ============================================================================
    // Type Filter Tests
    // ============================================================================

    public function test_execute_filters_by_type(): void
    {
        $this->createExpiredOtp();
        $otp2 = $this->createExpiredOtp();
        $otp2->type = 'login';
        $otp2->save();

        $response = $this->service->run(CleanupMfaDirective::class, ['--force', '--type=email_verification']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deleted 1 expired OTPs', $response->output);
        $this->assertCount(1, OneTimePassword::all());
    }
}
