<?php

declare(strict_types=1);

namespace Kani\Otp\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kani\Otp\Contracts\MustOtpChannels;
use Kani\Otp\Models\OneTimePassword;

/**
 * Notification class for sending OTP codes to users.
 *
 * This notification handles the delivery of one-time passwords via configured channels.
 * Channels can be determined from the OTP record itself, from the notifiable entity
 * implementing MustOtpChannels, or fallback to the default 'mail' channel.
 */
class OtpNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param OneTimePassword $otp The OTP model containing the code and metadata
     */
    public function __construct(
        private readonly OneTimePassword $otp
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * Priority order for channel resolution:
     * 1. Channels stored in the OTP record (if present and non-empty)
     * 2. Channels from notifiable entity implementing MustOtpChannels
     * 3. Fallback to ['mail']
     *
     * @param mixed $notifiable The entity receiving the notification
     * @return array<int, string> List of channel names
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
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable The entity receiving the notification
     * @return MailMessage The formatted email message
     */
    public function toMail($notifiable): MailMessage
    {
        $expiresIn = $this->otp->expires_at->diffInMinutes(now());
        $plainCode = $this->extractPlainCode();

        return (new MailMessage)
            ->subject('Votre code de vérification')
            ->greeting($this->buildGreeting($notifiable))
            ->line('Veuillez utiliser le code de vérification ci-dessous :')
            ->line('')
            ->line("<div style='text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; padding: 20px; background-color: #f3f4f6; border-radius: 8px;'>")
            ->line($plainCode)
            ->line('</div>')
            ->line('')
            ->line("Ce code expirera dans {$expiresIn} minute(s).")
            ->line('Si vous n\'avez pas demandé cette vérification, veuillez ignorer cet email.')
            ->salutation("Cordialement,\n" . config('app.name'));
    }

    /**
     * Extract channels from the OTP record if valid.
     *
     * @return array<int, string>|null Channel list or null if not set or invalid
     */
    private function getChannelsFromOtp(): ?array
    {
        if (!$this->hasValidChannelsInOtp()) {
            return null;
        }

        return $this->otp->channels;
    }

    /**
     * Check if the OTP record contains valid channels.
     */
    private function hasValidChannelsInOtp(): bool
    {
        return $this->otp->channels !== null
            && is_array($this->otp->channels)
            && !empty($this->otp->channels);
    }

    /**
     * Build the greeting line for the email.
     *
     * @param mixed $notifiable The entity receiving the notification
     */
    private function buildGreeting($notifiable): string
    {
        $name = $this->extractNotifiableName($notifiable);

        return "Bonjour {$name} !";
    }

    /**
     * Extract a human-readable name from the notifiable entity.
     *
     * @param mixed $notifiable The entity receiving the notification
     */
    private function extractNotifiableName($notifiable): string
    {
        return $notifiable->name ?? $notifiable->email ?? 'Utilisateur';
    }

    /**
     * Extract the plaintext OTP code from the stored hash.
     *
     * Note: This is a simplified approach assuming the plain code is stored
     * as a prefix of the hash. For production, the plain code should be
     * stored separately or generated deterministically.
     */
    private function extractPlainCode(): string
    {
        return substr($this->otp->token_hash, 0, 6);
    }
}
