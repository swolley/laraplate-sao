<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

use Modules\SAO\Drivers\Contracts\LogsCapability;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionCredentialResolver;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestStatus;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\ProjectBinding;

/**
 * The inbound transport for a `logs` connection's push deliveries: it turns one
 * raw HTTP webhook into signals through the connection's own driver.
 *
 * It verifies the delivery signature with the driver (so a forged body never
 * reaches the store), unpacks it into the driver's canonical events, and — for
 * each project bound to the connection with the `logs` capability — hands every
 * event to {@see SignalIngestService}, which resolves the group key (native or
 * computed) and records the occurrence. Every ingested event is stored as an
 * {@see IngestEvent}, deduped per (connection, delivery, binding, index) so a
 * re-delivery is recorded once and never re-ingested. The whole run is wrapped
 * in the {@see PipelineContext} so an error the ingest itself logs cannot loop
 * back in.
 */
final readonly class DriverWebhookIngestService
{
    public function __construct(
        private DriverRegistry $registry,
        private ConnectionCredentialResolver $resolver,
        private SignalIngestService $signalIngest,
        private PipelineContext $pipeline,
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function ingest(Connection $connection, string $deliveryId, string $rawBody, array $headers): WebhookIngestOutcome
    {
        return $this->pipeline->run(fn (): WebhookIngestOutcome => $this->handle($connection, $deliveryId, $rawBody, $headers));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function handle(Connection $connection, string $deliveryId, string $rawBody, array $headers): WebhookIngestOutcome
    {
        $driver = $connection->driver($this->registry);

        if (! $driver instanceof LogsCapability) {
            return WebhookIngestOutcome::unsupported('driver-has-no-logs-capability');
        }

        $context = new BindingContext($connection->connectionContext($this->resolver->resolve($connection)));

        if (! $driver->verifySignature($context, $rawBody, $headers)) {
            return WebhookIngestOutcome::unauthorized();
        }

        $events = $driver->unpack($context, $rawBody)->items;

        /** @var \Illuminate\Support\Collection<int, ProjectBinding> $bindings */
        $bindings = ProjectBinding::query()
            ->with('project')
            ->where('connection_id', $connection->getKey())
            ->where('capability', Capability::Logs)
            ->get();

        if ($bindings->isEmpty()) {
            $this->record($connection, $deliveryId, $this->decode($rawBody), null, null, IngestStatus::Discarded, 'no-logs-binding');

            return WebhookIngestOutcome::accepted('no-logs-binding');
        }

        $signalIds = [];

        foreach ($bindings as $binding) {
            $project = $binding->project;

            if (! $project instanceof Project) {
                continue;
            }

            foreach ($events as $index => $event) {
                $eventDeliveryId = "{$deliveryId}#{$binding->getKey()}#{$index}";

                $existing = IngestEvent::query()
                    ->where('connection_id', $connection->getKey())
                    ->where('delivery_id', $eventDeliveryId)
                    ->first();

                if ($existing instanceof IngestEvent) {
                    if ($existing->signal_id !== null) {
                        $signalIds[] = $existing->signal_id;
                    }

                    continue;
                }

                $signal = $this->signalIngest->ingest($project, $event);

                $this->record($connection, $eventDeliveryId, $event, $project->getKey(), $signal->getKey(), IngestStatus::Ingested, 'signal-recorded');

                $signalIds[] = $signal->getKey();
            }
        }

        return WebhookIngestOutcome::accepted('ingested', array_values(array_unique($signalIds)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(Connection $connection, string $deliveryId, array $payload, ?int $projectId, ?int $signalId, IngestStatus $status, string $outcome): void
    {
        IngestEvent::query()->create([
            'connection_id' => $connection->getKey(),
            'delivery_id' => $deliveryId,
            'payload' => $payload,
            'status' => $status,
            'outcome' => $outcome,
            'project_id' => $projectId,
            'signal_id' => $signalId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }
}
