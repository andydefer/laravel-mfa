<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Unit\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use AndyDefer\Mfa\Otp\Models\OneTimePassword;
use AndyDefer\Mfa\Otp\Notifications\OtpNotification;
use AndyDefer\Mfa\Tests\Support\TestCheckPoint;
use AndyDefer\Mfa\Tests\Support\TestUser;
use AndyDefer\Mfa\Tests\TestCase;

/**
 * Test suite for the OtpNotification class.
 *
 * Validates that OTP notifications are correctly configured and delivered
 * through the appropriate channels based on OTP configuration, notifiable
 * entity implementation, or fallback defaults.
 */
final class OtpNotificationTest extends TestCase
{
    use RefreshDatabase;

    private OneTimePassword $otp;

    private string $plainCode;

    private TestUser $testUser;

    /**
     * Setup test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set default locale to English for tests (nouvelle structure mfa.otp.localization)
        config()->set('mfa.otp.localization.locale', 'en');
        config()->set('mfa.otp.localization.fallback_locale', 'en');
        config()->set('mfa.otp.localization.supported_locales', ['fr', 'en']);

        $this->plainCode = '123456';

        $this->testUser = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->otp = OneTimePassword::create([
            'otpable_type' => $this->testUser->getMorphClass(),
            'otpable_id' => $this->testUser->getKey(),
            'token_hash' => Hash::make($this->plainCode),
            'type' => 'email_verification',
            'destination' => 'user@example.com',
            'channels' => null,
            'meta' => null,
            'attempts' => 0,
            'max_attempts' => 3,
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    /**
     * Test that the notification uses channels from OTP when present.
     */
    public function test_notification_uses_channels_from_otp_when_present(): void
    {
        // Arrange: Set custom channels on the OTP record
        $customChannels = ['sms', 'whatsapp'];
        $this->otp->update(['channels' => $customChannels]);

        // Act: Create notification and get delivery channels
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $channels = $notification->via($this->testUser);

        // Assert: OTP channels are used
        $this->assertEquals($customChannels, $channels);
    }

    /**
     * Test that the notification prioritizes OTP channels over notifiable channels.
     */
    public function test_notification_prioritizes_otp_channels_over_notifiable_channels(): void
    {
        // Arrange: Set OTP channels different from notifiable channels
        $otpChannels = ['sms'];
        $this->otp->update(['channels' => $otpChannels]);

        // Act: Create notification and get delivery channels
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $channels = $notification->via($this->testUser);

        // Assert: OTP channels take precedence over notifiable channels
        $this->assertEquals($otpChannels, $channels);
        $this->assertNotEquals($this->testUser->getOtpChannels(), $channels);
    }

    /**
     * Test that the notification uses channels from notifiable when OTP has no channels.
     */
    public function test_notification_uses_channels_from_notifiable_when_otp_has_no_channels(): void
    {
        // Arrange: Remove channels from OTP
        $this->otp->update(['channels' => null]);

        // Act: Create notification and get delivery channels
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $channels = $notification->via($this->testUser);

        // Assert: Notifiable channels are used as fallback
        $this->assertEquals($this->testUser->getOtpChannels(), $channels);
    }

    /**
     * Test that the notification uses channels from notifiable when OTP channels is empty array.
     */
    public function test_notification_uses_channels_from_notifiable_when_otp_channels_is_empty(): void
    {
        // Arrange: Set empty channels array on OTP
        $this->otp->update(['channels' => []]);

        // Act: Create notification and get delivery channels
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $channels = $notification->via($this->testUser);

        // Assert: Empty channels are ignored, notifiable channels are used
        $this->assertEquals($this->testUser->getOtpChannels(), $channels);
    }

    /**
     * Test that the notification falls back to 'mail' when no channels are available.
     */
    public function test_notification_falls_back_to_mail_when_no_channels_available(): void
    {
        // Arrange: Create a notifiable without MustOtpChannels interface
        $this->otp->update(['channels' => null]);

        $plainNotifiable = new class
        {
            public string $email = 'test@example.com';
        };

        // Act: Create notification and get delivery channels
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $channels = $notification->via($plainNotifiable);

        // Assert: Default mail channel is used
        $this->assertEquals(['mail'], $channels);
    }

    /**
     * Test that the notification works with TestCheckPoint model implementing MustOtpChannels.
     */
    public function test_notification_works_with_test_checkpoint_model(): void
    {
        // Arrange: Create a checkpoint and associate OTP with it
        $checkpoint = TestCheckPoint::create(['name' => 'Main Gate']);
        $this->otp->update([
            'otpable_type' => $checkpoint->getMorphClass(),
            'otpable_id' => $checkpoint->getKey(),
            'channels' => null,
        ]);

        // Act: Create notification and get delivery channels
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $channels = $notification->via($checkpoint);

        // Assert: Checkpoint's defined channels are used
        $this->assertEquals($checkpoint->getOtpChannels(), $channels);
    }

    /**
     * Test that the notification returns correct mail message in English.
     */
    public function test_notification_returns_correct_mail_message_in_english(): void
    {
        // Arrange: Ensure English locale in package config (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'en');
        config()->set('mfa.otp.localization.fallback_locale', 'en');

        // Act: Create notification and generate mail message
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $mailMessage = $notification->toMail($this->testUser);

        // Assert: Mail message contains English content
        $this->assertStringContainsString('Your verification code', $mailMessage->subject);
        $this->assertStringContainsString('Hello John Doe!', $mailMessage->greeting);
        $this->assertStringContainsString($this->plainCode, implode(' ', $mailMessage->introLines));
        $this->assertStringContainsString('This code will expire in', implode(' ', $mailMessage->introLines));
    }

    /**
     * Test that the notification returns correct mail message in French.
     */
    public function test_notification_returns_correct_mail_message_in_french(): void
    {
        // Arrange: Set French locale in package config (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'fr');
        config()->set('mfa.otp.localization.fallback_locale', 'en');
        config()->set('mfa.otp.localization.supported_locales', ['fr', 'en']);

        // Act: Create notification and generate mail message
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $mailMessage = $notification->toMail($this->testUser);

        // Assert: Mail message contains French content
        $this->assertStringContainsString('Votre code de vérification', $mailMessage->subject);
        $this->assertStringContainsString('Bonjour John Doe !', $mailMessage->greeting);
        $this->assertStringContainsString($this->plainCode, implode(' ', $mailMessage->introLines));
        $this->assertStringContainsString('Ce code expirera dans', implode(' ', $mailMessage->introLines));
    }

    /**
     * Test that the notification uses fallback name when notifiable has neither name nor email.
     */
    public function test_notification_uses_fallback_name_when_notifiable_has_no_name_and_no_email(): void
    {
        // Arrange: Set English locale (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'en');

        $notifiableWithoutNameOrEmail = new class
        {
            // No name, no email property
        };

        // Act: Create notification and generate mail message
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $mailMessage = $notification->toMail($notifiableWithoutNameOrEmail);

        // Assert: Fallback 'User' is used as name (English default)
        $this->assertStringContainsString('Hello User!', $mailMessage->greeting);
    }

    /**
     * Test that the notification uses fallback name in French when configured.
     */
    public function test_notification_uses_fallback_name_in_french_when_configured(): void
    {
        // Arrange: Set French locale (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'fr');
        config()->set('mfa.otp.localization.fallback_locale', 'en');
        config()->set('mfa.otp.localization.supported_locales', ['fr', 'en']);

        $notifiableWithoutNameOrEmail = new class
        {
            // No name, no email property
        };

        // Act: Create notification and generate mail message
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $mailMessage = $notification->toMail($notifiableWithoutNameOrEmail);

        // Assert: Fallback 'Utilisateur' is used as name (French)
        $this->assertStringContainsString('Bonjour Utilisateur !', $mailMessage->greeting);
    }

    /**
     * Test that the notification uses email as name when name property is not available.
     */
    public function test_notification_uses_email_when_name_not_available(): void
    {
        // Arrange: Set English locale (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'en');

        $plainNotifiable = new class
        {
            public string $email = 'test@example.com';
        };

        // Act: Create notification and generate mail message
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $mailMessage = $notification->toMail($plainNotifiable);

        // Assert: Email address is used as name
        $this->assertStringContainsString('Hello test@example.com!', $mailMessage->greeting);
    }

    /**
     * Test that the notification uses email as name in French when name not available.
     */
    public function test_notification_uses_email_in_french_when_name_not_available(): void
    {
        // Arrange: Set French locale (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'fr');
        config()->set('mfa.otp.localization.fallback_locale', 'en');
        config()->set('mfa.otp.localization.supported_locales', ['fr', 'en']);

        $plainNotifiable = new class
        {
            public string $email = 'test@example.com';
        };

        // Act: Create notification and generate mail message
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $mailMessage = $notification->toMail($plainNotifiable);

        // Assert: Email address is used as name with French greeting
        $this->assertStringContainsString('Bonjour test@example.com !', $mailMessage->greeting);
    }

    /**
     * Test that notification respects OTP channels even when notifiable implements MustOtpChannels.
     */
    public function test_notification_respects_otp_channels_over_notifiable_channels(): void
    {
        // Arrange: Set OTP channels different from default
        $otpChannels = ['whatsapp'];
        $this->otp->update(['channels' => $otpChannels]);

        // Act: Create notification and get delivery channels
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $channels = $notification->via($this->testUser);

        // Assert: OTP channels take priority over notifiable's getOtpChannels()
        $this->assertEquals($otpChannels, $channels);
        $this->assertNotEquals(['mail'], $channels);
    }

    /**
     * Test that notification handles OTP with null channels correctly.
     */
    public function test_notification_handles_null_channels_correctly(): void
    {
        // Arrange: Set null channels on OTP
        $this->otp->update(['channels' => null]);

        // Act: Create notification and get delivery channels
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $channels = $notification->via($this->testUser);

        // Assert: Notifiable channels are used
        $this->assertEquals($this->testUser->getOtpChannels(), $channels);
    }

    /**
     * Test that notification respects package locale configuration.
     */
    public function test_notification_respects_package_locale_configuration(): void
    {
        // Arrange: Set package locale to French (nouvelle structure)
        config()->set('mfa.otp.localization.locale', 'fr');
        config()->set('mfa.otp.localization.supported_locales', ['fr', 'en']);
        config()->set('mfa.otp.localization.fallback_locale', 'en');

        // Act: Create notification and generate mail message
        $notification = new OtpNotification($this->otp, $this->plainCode);
        $mailMessage = $notification->toMail($this->testUser);

        // Assert: French translations are used
        $this->assertStringContainsString('Votre code de vérification', $mailMessage->subject);
        $this->assertStringContainsString('Bonjour John Doe !', $mailMessage->greeting);
        $this->assertStringContainsString('Ce code expirera dans', implode(' ', $mailMessage->introLines));
    }
}
