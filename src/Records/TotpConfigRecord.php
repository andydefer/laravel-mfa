<?php

// src/Records/TotpConfigRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class TotpConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $period,
        public readonly int $digits,
        public readonly string $algorithm,
        public readonly ?string $issuer,
        public readonly int $window,
    ) {}
}
