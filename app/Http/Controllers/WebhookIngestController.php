<?php

declare(strict_types=1);

namespace Modules\SAO\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\SAO\Ingest\DriverWebhookIngestService;
use Modules\SAO\Models\Connection;

/**
 * The public entry point for a `logs` connection's push deliveries. It is
 * unauthenticated at the framework level on purpose: the delivery authenticates
 * itself with the driver's own signature/token scheme, checked inside
 * {@see DriverWebhookIngestService}. The controller only lifts the raw body and
 * headers off the request — the raw body is what the HMAC is computed over, so it
 * must not be re-encoded — derives a stable delivery id for idempotency, and maps
 * the service outcome onto an HTTP status.
 */
final class WebhookIngestController extends Controller
{
    /**
     * Headers a sender may use to make a delivery idempotent; the first present
     * one wins. With none, each delivery is treated as unique.
     *
     * @var list<string>
     */
    private const array DELIVERY_ID_HEADERS = [
        'X-Delivery-Id',
        'X-Request-Id',
        'X-GitHub-Delivery',
        'Idempotency-Key',
    ];

    public function __invoke(Request $request, Connection $connection, DriverWebhookIngestService $service): JsonResponse
    {
        $outcome = $service->ingest(
            $connection,
            $this->deliveryId($request),
            $request->getContent(),
            $this->headers($request),
        );

        return response()->json([
            'result' => $outcome->result,
            'signals' => $outcome->signalIds,
        ], $outcome->httpStatus);
    }

    private function deliveryId(Request $request): string
    {
        foreach (self::DELIVERY_ID_HEADERS as $header) {
            $value = $request->header($header);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return (string) Str::uuid();
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = is_array($values) ? (string) ($values[0] ?? '') : (string) $values;
        }

        return $headers;
    }
}
