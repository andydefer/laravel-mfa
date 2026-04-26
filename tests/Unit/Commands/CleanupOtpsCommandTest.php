<?php
// tests/Unit/Commands/CleanupOtpsCommandTest.php

declare(strict_types=1);

namespace Kani\Otp\Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kani\Otp\Models\OneTimePassword;
use Kani\Otp\Tests\TestCase;

/**
 * Test suite for the CleanupOtpsCommand.
 *
 * Validates that the OTP cleanup command properly removes expired, old verified,
 * used, and cancelled OTPs from the database according to configuration.
 *
 * @package Kani\Otp\Tests\Unit\Commands
 */
final class CleanupOtpsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the command can be instantiated.
     */
    public function test_command_can_be_instantiated(): void
    {
        // Act
        $command = $this->app->make(\Kani\Otp\Commands\CleanupOtpsCommand::class);

        // Assert
        $this->assertInstanceOf(\Kani\Otp\Commands\CleanupOtpsCommand::class, $command);
    }

    /**
     * Test that the command has the correct signature.
     */
    public function test_command_has_correct_signature(): void
    {
        // Act
        $command = $this->app->make(\Kani\Otp\Commands\CleanupOtpsCommand::class);

        // Assert
        $this->assertEquals('otp:cleanup', $command->getName());
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
        $this->artisan('otp:cleanup', ['--force' => true])->assertExitCode(0);

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
        $this->artisan('otp:cleanup', ['--force' => true, '--keep-expired' => true])->assertExitCode(0);

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
        $this->artisan('otp:cleanup', ['--force' => true, '--dry-run' => true])->assertExitCode(0);

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
        $this->artisan('otp:cleanup', ['--force' => true, '--type' => 'test'])->assertExitCode(0);

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
        $this->artisan('otp:cleanup', ['--force' => true, '--days' => 30])->assertExitCode(0);

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
        $this->artisan('otp:cleanup', ['--force' => true])->assertExitCode(0);
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
        $this->artisan('otp:cleanup')
            ->expectsQuestion('This will permanently delete expired and old OTPs. Do you wish to continue?', 'yes')
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
        $this->artisan('otp:cleanup')
            ->expectsConfirmation('This will permanently delete expired and old OTPs. Do you wish to continue?', 'no')
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
        $this->artisan('otp:cleanup', ['--force' => true])->assertExitCode(0);

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
        $this->artisan('otp:cleanup', ['--force' => true])->assertExitCode(0);

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
        $this->artisan('otp:cleanup', ['--force' => true])->assertExitCode(0);

        // Assert
        $this->assertDatabaseMissing('one_time_passwords', [
            'id' => $oldCancelledOtp->id,
        ]);
    }
}
