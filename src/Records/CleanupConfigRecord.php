<?php

// src/Records/CleanupConfigRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class CleanupConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $auto_cleanup,
        public readonly int $frequency,
        public readonly int $retention_days,
    ) {}
}
