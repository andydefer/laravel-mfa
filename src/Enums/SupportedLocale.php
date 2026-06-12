<?php

// src/Enums/SupportedLocale.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Enums;

enum SupportedLocale: string
{
    case FRENCH = 'fr';
    case ENGLISH = 'en';

    /**
     * Get the display name of the locale.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::FRENCH => 'Français',
            self::ENGLISH => 'English',
        };
    }

    /**
     * Create from string value.
     */
    public static function fromString(string $value): ?self
    {
        return match ($value) {
            'fr' => self::FRENCH,
            'en' => self::ENGLISH,
            default => null,
        };
    }
}
