<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

use Modules\SAO\Models\SourceProfile;

/**
 * Turns a raw payload into canonical fields by applying a profile's field
 * bindings (`canonical field => dot-path`). Only bindings that resolve to a
 * non-null value are emitted, so absent fields stay absent rather than nulling
 * the canonical shape.
 */
final class PayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(SourceProfile $profile, array $payload): array
    {
        $canonical = [];

        foreach ($profile->field_bindings as $field => $path) {
            $value = data_get($payload, $path);

            if ($value !== null) {
                $canonical[$field] = $value;
            }
        }

        return $canonical;
    }
}
