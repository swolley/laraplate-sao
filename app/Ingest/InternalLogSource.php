<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

/**
 * The optional host-app log source. It reads the application's own log records
 * and, as loop protection layer 1, **discards every record carrying the
 * pipeline-origin marker regardless of module** — so an error the ingest
 * pipeline itself emitted never feeds back in. Each discard carries an explicit
 * reason (reliable silence).
 */
final class InternalLogSource
{
    public const string DISCARD_PIPELINE_ORIGIN = 'pipeline-origin';

    /**
     * @param  array<string, mixed>  $record
     */
    public function carriesPipelineMarker(array $record): bool
    {
        $context = $record['context'] ?? [];

        return is_array($context) && ($context[PipelineContext::ORIGIN_KEY] ?? false) === true;
    }

    /**
     * Partition records into those safe to ingest and those discarded, each
     * discard tagged with its reason.
     *
     * @param  list<array<string, mixed>>  $records
     * @return array{ingestible: list<array<string, mixed>>, discarded: list<array{reason: string, record: array<string, mixed>}>}
     */
    public function select(array $records): array
    {
        $ingestible = [];
        $discarded = [];

        foreach ($records as $record) {
            if ($this->carriesPipelineMarker($record)) {
                $discarded[] = ['reason' => self::DISCARD_PIPELINE_ORIGIN, 'record' => $record];

                continue;
            }

            $ingestible[] = $record;
        }

        return ['ingestible' => $ingestible, 'discarded' => $discarded];
    }
}
