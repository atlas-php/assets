<?php

declare(strict_types=1);

namespace Atlas\Assets\Http\Controllers;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Services\AssetRetrievalService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class AssetStreamController
 *
 * Streams an asset file via a signed route fallback when disks cannot issue
 * temporary URLs directly.
 * PRD Reference: Atlas Assets Overview — Retrieval APIs.
 */
class AssetStreamController
{
    public function __construct(
        private readonly AssetRetrievalService $retrievalService
    ) {}

    public function __invoke(Asset $asset): StreamedResponse
    {
        return $this->retrievalService->stream($asset);
    }
}
