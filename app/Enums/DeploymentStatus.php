<?php

declare(strict_types=1);

namespace Modules\SAO\Enums;

/**
 * The lifecycle of a single deployment/rollout as reported by the source.
 *
 * `started` is the only non-terminal state; the rest are outcomes. Only a
 * terminal `succeeded` advances the environment's version census — a `failed`
 * or `rolled_back` deploy is recorded as history but never asserted as running.
 */
enum DeploymentStatus: string
{
    /** The deploy/rollout began; not yet an outcome. */
    case Started = 'started';

    /** Reached the target and stayed — the running version. */
    case Succeeded = 'succeeded';

    /** Aborted before completion; the environment keeps its prior version. */
    case Failed = 'failed';

    /** Rolled back after starting; the environment reverted to its prior version. */
    case RolledBack = 'rolled_back';

    /** Overtaken by a newer deploy before finishing. */
    case Superseded = 'superseded';

    /**
     * Whether the deploy has reached an outcome (anything but `started`).
     */
    public function isTerminal(): bool
    {
        return $this !== self::Started;
    }

    /**
     * Whether this outcome means the reported version is what now runs.
     */
    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
