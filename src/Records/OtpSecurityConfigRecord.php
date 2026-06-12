<?php

// src/Records/OtpSecurityConfigRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Traits\Hydratable;

final class OtpSecurityConfigRecord extends AbstractRecord
{
    use Hydratable;

    public function __construct(
        public readonly int $rate_limit_requests,
        public readonly int $rate_limit_verifications,
        public readonly int $rate_limit_decay_minutes,
        public readonly int $failed_verification_decay_seconds,
        public readonly int $rate_limit_hit_decay_seconds,
    ) {}
}
