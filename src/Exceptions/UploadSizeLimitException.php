<?php

declare(strict_types=1);

namespace Atlas\Assets\Exceptions;

use RuntimeException;

/**
 * Class UploadSizeLimitException
 *
 * Thrown when an uploaded file exceeds the maximum allowed size defined either
 * in configuration or via per-upload overrides.
 * PRD Reference: Atlas Assets Overview — Uploading & Updating.
 */
class UploadSizeLimitException extends RuntimeException {}
