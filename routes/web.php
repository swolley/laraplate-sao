<?php

use Illuminate\Support\Facades\Route;
use Modules\SAO\Http\Controllers\SAOController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('saos', SAOController::class)->names('sao');
});
