<?php
// src/Contracts/CodeGeneratorInterface.php

declare(strict_types=1);

namespace Kani\Otp\Contracts;

/**
 * Contract for generating OTP codes.
 *
 * This abstraction allows different code generation strategies to be
 * injected into the OtpService, making it testable and extensible.
 */
interface CodeGeneratorInterface
{
    /**
     * Generate a one-time password code.
     *
     * @return string The generated code (typically 6 digits)
     */
    public function generate(): string;
}
