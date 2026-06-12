<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for pure unit tests that don't need Laravel.
 * No Laravel bootstrap, no database, no migrations.
 */
abstract class UnitTestCase extends BaseTestCase
{
    // Rien du tout - tests purement unitaires
}
