<?php
// src/Otp/Records/OneTimePasswordFilterRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class OneTimePasswordFilterRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $otpable_type = null,
        public readonly ?int $otpable_id = null,
        public readonly ?string $token_hash = null,
        public readonly ?string $type = null,
        public readonly ?string $destination = null,
        public readonly ?bool $is_expired = null,
        public readonly ?bool $is_verified = null,
        public readonly ?bool $is_used = null,
        public readonly ?bool $is_cancelled = null,
        public readonly ?DateTimeVO $created_before = null,
        public readonly ?DateTimeVO $created_after = null,
        public readonly ?DateTimeVO $expires_before = null,
        public readonly ?DateTimeVO $expires_after = null,
        public readonly ?int $limit = null,
        public readonly ?string $sort_by = null,
        public readonly ?string $sort_direction = null,
    ) {}
}
