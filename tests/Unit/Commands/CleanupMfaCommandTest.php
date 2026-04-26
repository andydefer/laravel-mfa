<?php

// tests/Unit/Commands/CleanupMfaCommandTest.php

declare(strict_types=1);

namespace Kani\Mfa\Tests\Unit\Commands;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kani\Mfa\Core\Commands\CleanupMfaCommand;
use Kani\Mfa\Otp\Models\OneTimePassword;
use Kani\Mfa\Tests\TestCase;
use Kani\Mfa\Totp\Services\TOTPService;

/**
 * Test suite for the CleanupMfaCommand.
 *
 * Validates that the MFA cleanup command properly removes expired OTPs,
 * old verified/used/cancelled OTPs, and old 2FA secrets from the database.
 */
final class CleanupMfaCommandTest extends TestCase
{
    use RefreshDatabase;

    private TOTPService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = new TOTPService;
    }

    /**
     * Test that the command can be instantiated.
     */
    public function test_command_can_be_instantiated(): void
    {
        // Act
        $command = $this->app->make(CleanupMfaCommand::class);

        // Assert
        $this->assertInstanceOf(CleanupMfaCommand::class, $command);
    }

    /**
     * Test that the command has the correct signature.
     */
    public function test_command_has_correct_signature(): void
    {
        // Act
        $command = $this->app->make(CleanupMfaCommand::class);

        // Assert
        $this->assertEquals('mfa:cleanup', $command->getName());
    }

    /**
     * Test that the command deletes expired OTPs when force option is used.
     */
    public function test_command_deletes_expired_otps(): void
    {
        // Arrange
        $otp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', '123456'),
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->subDay(),
            'max_attempts' => 3,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true])->assertExitCode(0);

        // Assert
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => $otp->id,
        ]);
    }

    /**
     * Test that the command respects the keep-expired option.
     */
    public function test_command_respects_keep_expired_option(): void
    {
        // Arrange
        $otp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', '123456'),
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->subDay(),
            'max_attempts' => 3,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true, '--keep-expired' => true])->assertExitCode(0);

        // Assert
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otp->id,
        ]);
    }

    /**
     * Test that the command respects the dry-run option.
     */
    public function test_command_respects_dry_run_option(): void
    {
        // Arrange
        $otp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', '123456'),
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->subDay(),
            'max_attempts' => 3,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true, '--dry-run' => true])->assertExitCode(0);

        // Assert
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otp->id,
        ]);
    }

    /**
     * Test that the command filters OTPs by type.
     */
    public function test_command_filters_by_type(): void
    {
        // Arrange
        $otp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', '123456'),
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->subDay(),
            'max_attempts' => 3,
        ]);

        $otp2 = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', '654321'),
            'type' => 'different_type',
            'destination' => 'test2@example.com',
            'expires_at' => now()->subDay(),
            'max_attempts' => 3,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true, '--type' => 'test'])->assertExitCode(0);

        // Assert
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => $otp->id,
        ]);
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otp2->id,
        ]);
    }

    /**
     * Test that the command respects custom retention days.
     */
    public function test_command_respects_custom_retention_days(): void
    {
        // Arrange
        $oldOtp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', 'old_hash'),
            'type' => 'test',
            'destination' => 'old@example.com',
            'expires_at' => now()->addDay(),
            'verified_at' => now()->subDays(40),
            'max_attempts' => 3,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true, '--days' => 30])->assertExitCode(0);

        // Assert
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => $oldOtp->id,
        ]);
    }

    /**
     * Test that the command returns success exit code.
     */
    public function test_command_returns_success_exit_code(): void
    {
        // Act & Assert
        $this->artisan('mfa:cleanup', ['--force' => true])->assertExitCode(0);
    }

    /**
     * Test that the command requires confirmation when force is not used.
     */
    public function test_command_requires_confirmation_without_force(): void
    {
        // Arrange
        $otp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', '123456'),
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->subDay(),
            'max_attempts' => 3,
        ]);

        // Act & Assert
        $this->artisan('mfa:cleanup')
            ->expectsQuestion('This will permanently delete expired OTPs and old 2FA secrets. Do you wish to continue?', 'yes')
            ->assertExitCode(0);

        // Assert
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => $otp->id,
        ]);
    }

    /**
     * Test that the command cancels when user declines confirmation.
     */
    public function test_command_cancels_when_user_declines(): void
    {
        // Arrange
        $otp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', '123456'),
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->subDay(),
            'max_attempts' => 3,
        ]);

        $otpId = $otp->id;

        // Act
        $this->artisan('mfa:cleanup')
            ->expectsConfirmation('This will permanently delete expired OTPs and old 2FA secrets. Do you wish to continue?', 'no')
            ->assertExitCode(0);

        // Assert
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otpId,
        ]);
    }

    /**
     * Test that the command deletes old verified OTPs.
     */
    public function test_command_deletes_old_verified_otps(): void
    {
        // Arrange
        $oldVerifiedOtp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', 'verified_hash'),
            'type' => 'test',
            'destination' => 'verified@example.com',
            'expires_at' => now()->addDay(),
            'verified_at' => now()->subDays(60),
            'max_attempts' => 3,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true])->assertExitCode(0);

        // Assert
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => $oldVerifiedOtp->id,
        ]);
    }

    /**
     * Test that the command deletes old used OTPs.
     */
    public function test_command_deletes_old_used_otps(): void
    {
        // Arrange
        $oldUsedOtp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', 'used_hash'),
            'type' => 'test',
            'destination' => 'used@example.com',
            'expires_at' => now()->addDay(),
            'used_at' => now()->subDays(60),
            'max_attempts' => 3,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true])->assertExitCode(0);

        // Assert
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => $oldUsedOtp->id,
        ]);
    }

    /**
     * Test that the command deletes old cancelled OTPs.
     */
    public function test_command_deletes_old_cancelled_otps(): void
    {
        // Arrange
        $oldCancelledOtp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', 'cancelled_hash'),
            'type' => 'test',
            'destination' => 'cancelled@example.com',
            'expires_at' => now()->addDay(),
            'cancelled_at' => now()->subDays(60),
            'max_attempts' => 3,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true])->assertExitCode(0);

        // Assert
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => $oldCancelledOtp->id,
        ]);
    }

    /**
     * Test that the command deletes disabled 2FA secrets older than retention period.
     * Utilisation de DB::table pour forcer la date car les casts de Carbon peuvent interférer.
     */
    public function test_command_deletes_old_disabled_2fa_secrets(): void
    {
        // Arrange: Utiliser DB::table pour insérer avec une date précise
        $secretId = DB::table('two_factor_secrets')->insertGetId([
            'authenticatable_type' => 'test',
            'authenticatable_id' => 1,
            'secret' => $this->totpService->generateSecret(),
            'is_enabled' => false,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
            'confirmed_at' => null,
            'last_used_at' => null,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true])->assertExitCode(0);

        // Assert: Le secret doit être supprimé
        $this->assertDatabaseMissing('two_factor_secrets', [
            'id' => $secretId,
        ]);
    }

    /**
     * Test that the command does NOT delete recent disabled 2FA secrets.
     */
    public function test_command_does_not_delete_recent_disabled_2fa_secrets(): void
    {
        // Arrange: Utiliser DB::table pour insérer une date récente
        $secretId = DB::table('two_factor_secrets')->insertGetId([
            'authenticatable_type' => 'test',
            'authenticatable_id' => 1,
            'secret' => $this->totpService->generateSecret(),
            'is_enabled' => false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'confirmed_at' => null,
            'last_used_at' => null,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true])->assertExitCode(0);

        // Assert: Le secret doit être conservé
        $this->assertDatabaseHas('two_factor_secrets', [
            'id' => $secretId,
        ]);
    }

    /**
     * Test that the command deletes old unused confirmed 2FA secrets.
     */
    public function test_command_deletes_old_unused_confirmed_2fa_secrets(): void
    {
        // Arrange: Utiliser DB::table pour insérer avec une date précise
        $secretId = DB::table('two_factor_secrets')->insertGetId([
            'authenticatable_type' => 'test',
            'authenticatable_id' => 1,
            'secret' => $this->totpService->generateSecret(),
            'is_enabled' => true,
            'confirmed_at' => '2023-01-01 00:00:00',
            'last_used_at' => null,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true])->assertExitCode(0);

        // Assert: Le secret doit être supprimé
        $this->assertDatabaseMissing('two_factor_secrets', [
            'id' => $secretId,
        ]);
    }

    /**
     * Test that the command respects otp-only option.
     */
    public function test_command_respects_otp_only_option(): void
    {
        // Arrange
        $otp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', '123456'),
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->subDay(),
            'max_attempts' => 3,
        ]);

        $secretId = DB::table('two_factor_secrets')->insertGetId([
            'authenticatable_type' => 'test',
            'authenticatable_id' => 1,
            'secret' => $this->totpService->generateSecret(),
            'is_enabled' => false,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true, '--otp-only' => true])->assertExitCode(0);

        // Assert: L'OTP est supprimé, mais le secret 2FA reste
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => $otp->id,
        ]);
        $this->assertDatabaseHas('two_factor_secrets', [
            'id' => $secretId,
        ]);
    }

    /**
     * Test that the command respects totp-only option.
     */
    public function test_command_respects_totp_only_option(): void
    {
        // Arrange
        $otp = OneTimePassword::create([
            'otpable_type' => 'test',
            'otpable_id' => 1,
            'token_hash' => hash('sha256', '123456'),
            'type' => 'test',
            'destination' => 'test@example.com',
            'expires_at' => now()->subDay(),
            'max_attempts' => 3,
        ]);

        $secretId = DB::table('two_factor_secrets')->insertGetId([
            'authenticatable_type' => 'test',
            'authenticatable_id' => 1,
            'secret' => $this->totpService->generateSecret(),
            'is_enabled' => false,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
            'confirmed_at' => null,
            'last_used_at' => null,
        ]);

        // Act
        $this->artisan('mfa:cleanup', ['--force' => true, '--totp-only' => true])->assertExitCode(0);

        // Assert: L'OTP reste, mais le secret 2FA est supprimé
        $this->assertDatabaseHas('one_time_passwords', [
            'id' => $otp->id,
        ]);
        $this->assertDatabaseMissing('two_factor_secrets', [
            'id' => $secretId,
        ]);
    }
}
