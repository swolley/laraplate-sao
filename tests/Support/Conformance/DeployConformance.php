<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Conformance;

use Modules\SAO\Drivers\Contracts\DeployCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\DeployEvent;
use Modules\SAO\Enums\DeploymentStatus;

/**
 * The battery every `deploy` driver must pass: a correctly-signed delivery
 * verifies and unpacks into well-formed {@see DeployEvent}s, and a delivery with
 * no signature is rejected. Each driver's own test supplies a representative
 * payload and the valid headers for its signing scheme.
 *
 * @param  array<string, string>  $validHeaders
 */
final class DeployConformance
{
    /**
     * @param  array<string, string>  $validHeaders
     */
    public static function assert(DeployCapability $driver, BindingContext $context, string $payload, array $validHeaders): void
    {
        expect($driver->verifySignature($context, $payload, $validHeaders))->toBeTrue()
            ->and($driver->verifySignature($context, $payload, []))->toBeFalse();

        $events = $driver->unpack($context, $payload);

        expect($events)->not->toBeEmpty();

        foreach ($events as $event) {
            expect($event)->toBeInstanceOf(DeployEvent::class)
                ->and($event->version)->not->toBe('')
                ->and($event->status)->toBeInstanceOf(DeploymentStatus::class);
        }
    }
}
