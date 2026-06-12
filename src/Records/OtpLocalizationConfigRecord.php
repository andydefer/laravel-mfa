<?php

// src/Records/OtpLocalizationConfigRecord.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Mfa\Collections\SupportedLocaleCollection;
use AndyDefer\Mfa\Enums\SupportedLocale;

final class OtpLocalizationConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $locale,
        public readonly SupportedLocaleCollection $supported_locales,
        public readonly SupportedLocale $fallback_locale,
    ) {}
}
