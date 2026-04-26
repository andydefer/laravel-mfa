<?php

declare(strict_types=1);

namespace Kani\Mfa\Otp\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kani\Mfa\Core\Helpers\TranslationHelper;
use Kani\Mfa\Otp\Contracts\MustOtpChannels;
use Kani\Mfa\Otp\Models\OneTimePassword;

/**
 * Notification for delivering OTP codes to users via configured channels.
 *
 * This notification dynamically determines which channels to use (mail, sms, etc.)
 * based on: 1) OTP record's stored channels, 2) Notifiable's channel preferences,
 * or 3) falls back to default 'mail' channel.
 *
 * Supports localization for multi-language applications.
 */
final class OtpNotification extends Notification
{
    use Queueable;

    /**
     * Create a new OTP notification instance.
     *
     * @param  OneTimePassword  $otp  The OTP model instance containing metadata
     * @param  string  $plainCode  The plain text OTP code (not stored in DB)
     */
    public function __construct(
        private readonly OneTimePassword $otp,
        private readonly string $plainCode
    ) {}

    /**
     * Determine the delivery channels for the notification.
     *
     * Priority order:
     * 1. Channels stored in OTP record (from original request)
     * 2. Channels defined by notifiable via MustOtpChannels contract
     * 3. Default ['mail'] channel
     *
     * @param  mixed  $notifiable  The entity receiving the notification
     * @return array<int, string> List of delivery channels
     */
    public function via($notifiable): array
    {
        $channelsFromOtp = $this->getChannelsFromOtp();

        if ($channelsFromOtp !== null) {
            return $channelsFromOtp;
        }

        if ($notifiable instanceof MustOtpChannels) {
            return $notifiable->getOtpChannels();
        }

        return ['mail'];
    }

    /**
     * Build the mail message for OTP delivery.
     *
     * @param  mixed  $notifiable  The entity receiving the notification
     * @return MailMessage The configured email message
     */
    public function toMail($notifiable): MailMessage
    {
        $expiresIn = $this->otp->expires_at->diffInMinutes(now());
        $greeting = $this->buildGreeting($notifiable);
        $codeBlock = $this->buildCodeBlock();

        return (new MailMessage)
            ->subject(TranslationHelper::trans('messages.subject', ['app_name' => config('app.name')]))
            ->greeting($greeting)
            ->line(TranslationHelper::trans('messages.intro'))
            ->line('')
            ->line($codeBlock)
            ->line('')
            ->line(TranslationHelper::trans('messages.expires_in', ['minutes' => $expiresIn]))
            ->line(TranslationHelper::trans('messages.ignore_request'))
            ->salutation(TranslationHelper::trans('messages.salutation', ['app_name' => config('app.name')]));
    }

    /**
     * Extract and validate channels stored in the OTP record.
     *
     * @return array<int, string>|null Channels array or null if invalid/empty
     */
    private function getChannelsFromOtp(): ?array
    {
        $channels = $this->otp->channels;

        if ($channels === null || ! is_array($channels) || empty($channels)) {
            return null;
        }

        return $channels;
    }

    /**
     * Build the personalized greeting for the notification.
     *
     * @param  mixed  $notifiable  The entity receiving the notification
     * @return string Personalized greeting
     */
    private function buildGreeting($notifiable): string
    {
        $name = $this->extractNotifiableName($notifiable);
        $template = TranslationHelper::trans('messages.greeting');

        return sprintf($template, $name);
    }

    /**
     * Extract a human-readable name from the notifiable entity.
     *
     * @param  mixed  $notifiable  The entity receiving the notification
     * @return string User's name, email, or fallback
     */
    private function extractNotifiableName($notifiable): string
    {
        return $notifiable->name
            ?? $notifiable->email
            ?? TranslationHelper::trans('messages.default_user_name');
    }

    /**
     * Build the HTML code block for email display.
     *
     * @return string HTML string containing the styled OTP code
     */
    private function buildCodeBlock(): string
    {
        return sprintf(
            "<div style='text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; padding: 20px; background-color: #f3f4f6; border-radius: 8px;'>\n%s\n</div>",
            $this->plainCode
        );
    }
}
