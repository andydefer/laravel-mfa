<?php

// src/Collections/OtpAttemptRecordCollection.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Mfa\Records\OtpAttemptRecord;

final class OtpAttemptRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(OtpAttemptRecord::class);
    }

    public function getSuccessfulAttempts(): self
    {
        return $this->filter(fn (OtpAttemptRecord $attempt) => $attempt->success);
    }

    public function getFailedAttempts(): self
    {
        return $this->filter(fn (OtpAttemptRecord $attempt) => ! $attempt->success);
    }

    public function getLastAttempt(): ?OtpAttemptRecord
    {
        $last = $this->last();

        return $last instanceof OtpAttemptRecord ? $last : null;
    }
}
