<?php

declare(strict_types=1);

namespace Atlas\Assets\Exceptions;

use InvalidArgumentException;

/**
 * Class DisallowedExtensionException
 *
 * Represents extension validation failures during uploads when the configured
 * whitelist/blocklist rules are violated.
 * PRD Reference: Atlas Assets Overview — Uploading & Updating.
 */
class DisallowedExtensionException extends InvalidArgumentException {}
