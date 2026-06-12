<?php

// src/Records/OtpConfigRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class OtpConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $default_expiry_minutes,
        public readonly int $default_max_attempts,
    ) {}
}
