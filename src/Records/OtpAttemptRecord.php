<?php

// src/Records/OtpAttemptRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class OtpAttemptRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $attempt_number,
        public readonly string $code_provided,
        public readonly bool $success,
        public readonly ?string $error_message = null,
        public readonly ?DateTimeVO $attempted_at = null,
    ) {}
}
