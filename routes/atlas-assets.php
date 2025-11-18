<?php

declare(strict_types=1);

use Atlas\Assets\Http\Controllers\AssetStreamController;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Middleware\SubstituteBindings;

Route::middleware(['signed', SubstituteBindings::class])->group(function (): void {
    Route::get('/atlas-assets/stream/{asset}', AssetStreamController::class)
        ->name('atlas-assets.stream');
});
