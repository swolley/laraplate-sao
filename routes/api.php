<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\SAO\Http\Controllers\WebhookIngestController;

/*
 * Inbound push endpoint for a `logs` connection. Unauthenticated at the
 * framework level by design — the delivery is authenticated by the driver's own
 * signature/token scheme inside the ingest service. The `{connection}` segment
 * binds the receiving connection by id.
 */
Route::post('webhooks/{connection}', WebhookIngestController::class)->name('webhooks.ingest');
