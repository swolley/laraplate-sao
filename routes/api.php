<?php

use Illuminate\Support\Facades\Route;
use Modules\SAO\Http\Controllers\SAOController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('saos', SAOController::class)->names('sao');
});
