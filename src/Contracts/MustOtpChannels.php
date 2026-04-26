<?php

declare(strict_types=1);

namespace Kani\Otp\Contracts;

/**
 * Interface for entities that require OTP channel configuration.
 *
 * Implementing this interface allows an entity (typically a User model or similar)
 * to define which communication channels should be used for OTP delivery
 * (e.g., email, SMS, WhatsApp) and any channel-specific preferences.
 */
interface MustOtpChannels
{
    /**
     * Get the OTP delivery channels configured for this entity.
     *
     * @return array<int, string> List of channel names (e.g., ['email', 'sms', 'whatsapp'])
     */
    public function getOtpChannels(): array;
}
