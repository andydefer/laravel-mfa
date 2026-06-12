<?php
// src/Otp/Records/OneTimePasswordRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class OneTimePasswordRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,  // ✅ UUID est un string
        public readonly ?string $otpable_type = null,
        public readonly ?int $otpable_id = null,
        public readonly ?string $token_hash = null,
        public readonly ?string $type = null,
        public readonly ?string $destination = null,
        public readonly ?array $channels = null,
        public readonly ?StrictDataObject $meta = null,
        public readonly ?int $attempts = null,
        public readonly ?int $max_attempts = null,
        public readonly ?DateTimeVO $expires_at = null,
        public readonly ?DateTimeVO $verified_at = null,
        public readonly ?DateTimeVO $used_at = null,
        public readonly ?DateTimeVO $cancelled_at = null,
        public readonly ?DateTimeVO $created_at = null,
        public readonly ?DateTimeVO $updated_at = null,
    ) {}
}
