<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Support;

/**
 * Class PregReplaceHook
 *
 * Allows namespace-level preg_replace overrides during tests.
 * PRD Reference: Atlas Assets Overview — Path Resolution.
 */
final class PregReplaceHook
{
    public static bool $shouldReturnNull = false;
}
