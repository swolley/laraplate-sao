<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Conformance;

use Modules\SAO\Attribution\CodeReference;
use Modules\SAO\Drivers\Contracts\CodeEventCapability;
use Modules\SAO\Drivers\Support\BindingContext;

/**
 * The battery every `code` driver must pass: a correctly-signed delivery verifies
 * and unpacks into well-formed {@see CodeReference}s (each with a non-empty
 * identifier), and a delivery with no signature is rejected. Each driver's own
 * test supplies a representative payload and the valid headers for its scheme.
 */
final class CodeConformance
{
    /**
     * @param  array<string, string>  $validHeaders
     */
    public static function assert(CodeEventCapability $driver, BindingContext $context, string $payload, array $validHeaders): void
    {
        expect($driver->verifySignature($context, $payload, $validHeaders))->toBeTrue()
            ->and($driver->verifySignature($context, $payload, []))->toBeFalse();

        $references = $driver->unpack($context, $payload);

        expect($references)->not->toBeEmpty();

        foreach ($references as $reference) {
            expect($reference)->toBeInstanceOf(CodeReference::class)
                ->and($reference->identifier)->not->toBe('');
        }
    }
}
