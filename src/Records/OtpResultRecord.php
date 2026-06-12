<?php

// src/Records/OtpResultRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class OtpResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $is_success,
        public readonly string $message,
        public readonly ?StrictDataObject $data = null,
        public readonly ?DateTimeVO $occurred_at = null,
    ) {}
}
