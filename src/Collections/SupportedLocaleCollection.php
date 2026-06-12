<?php

// src/Collections/SupportedLocaleCollection.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Mfa\Enums\SupportedLocale;

/**
 * Type-safe collection for SupportedLocale instances.
 *
 * @extends AbstractTypedCollection<SupportedLocale>
 */
final class SupportedLocaleCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SupportedLocale::class);
    }

    /**
     * Create collection from array of string values.
     *
     * @param  array<int, string>  $values
     */
    public static function fromStrings(array $values): self
    {
        $collection = new self;

        foreach ($values as $value) {
            $locale = SupportedLocale::fromString($value);
            if ($locale !== null) {
                $collection->add($locale);
            }
        }

        return $collection;
    }
}
