<?php

declare(strict_types=1);

use Atlas\Assets\Http\Controllers\AssetStreamController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::middleware(['signed', SubstituteBindings::class])->group(function (): void {
    Route::get('/atlas-assets/stream/{asset}', AssetStreamController::class)
        ->name('atlas-assets.stream');
});
