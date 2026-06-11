<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Services;

use AndyDefer\Mfa\Otp\Contracts\CodeGeneratorInterface;

/**
 * Default OTP code generator producing random 6-digit numeric codes.
 *
 * This implementation generates cryptographically secure random numbers
 * between 0 and 999,999, then pads them with leading zeros to ensure
 * a consistent 6-digit format (e.g., "001234").
 */
final class DefaultCodeGenerator implements CodeGeneratorInterface
{
    /**
     * Generate a random 6-digit OTP code.
     *
     * The code is generated using random_int() which provides
     * cryptographically secure random numbers suitable for security tokens.
     *
     * @return string A 6-digit numeric code with leading zeros if necessary
     */
    public function generate(): string
    {
        $randomNumber = random_int(0, 999999);

        return str_pad(
            string: (string) $randomNumber,
            length: 6,
            pad_string: '0',
            pad_type: STR_PAD_LEFT
        );
    }
}
