<?php

// src/Records/RecoveryCodesConfigRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class RecoveryCodesConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $characters,
        public readonly int $default_count,
        public readonly int $default_length,
        public readonly string $hash_algorithm,
    ) {}
}
