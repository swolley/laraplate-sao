<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\External;

use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Contracts\LogsCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConfigurationField;
use Modules\SAO\Drivers\Support\DriverConfigurationSchema;
use Modules\SAO\Drivers\Support\LogsDriverBoilerplate;
use Modules\SAO\Drivers\Support\Page;
use Override;

/**
 * The `logs` driver for Grafana alerting (covering Loki/Prometheus alerts routed
 * through Grafana), over its contact-point webhook. Grafana is an alert
 * aggregator with no per-error native key, so `carriesNativeGroupKey()` is false
 * and SAO fingerprints each alert itself. Deliveries carry a shared token the
 * contact point is configured to send in a header.
 */
final readonly class GrafanaDriver implements DriverInterface, LogsCapability
{
    use LogsDriverBoilerplate;

    #[Override]
    public function key(): string
    {
        return 'grafana';
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('secret', 'string', 'Shared token Grafana sends in the webhook header', required: true, secret: true),
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Override]
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool
    {
        return $this->tokenVerified($context, $headers, 'X-Grafana-Token');
    }

    #[Override]
    public function unpack(BindingContext $context, string $payload): Page
    {
        $decoded = $this->decode($payload);

        /** @var list<array<string, mixed>> $alerts */
        $alerts = is_array($decoded['alerts'] ?? null) ? array_values(array_filter($decoded['alerts'], 'is_array')) : [];

        $items = [];

        foreach ($alerts as $alert) {
            /** @var array<string, mixed> $labels */
            $labels = is_array($alert['labels'] ?? null) ? $alert['labels'] : [];

            /** @var array<string, mixed> $annotations */
            $annotations = is_array($alert['annotations'] ?? null) ? $alert['annotations'] : [];

            $message = $this->stringOrNull($annotations['summary'] ?? $annotations['description'] ?? $labels['alertname'] ?? null);

            if ($message === null) {
                continue;
            }

            $items[] = [
                'source' => $this->key(),
                'message' => $message,
                'level' => $this->stringOrNull($labels['severity'] ?? null),
                'environment' => $this->stringOrNull($labels['env'] ?? $labels['environment'] ?? null),
                'url' => $this->stringOrNull($alert['generatorURL'] ?? null),
                'raw' => $alert,
            ];
        }

        return new Page($items);
    }

    #[Override]
    public function carriesNativeGroupKey(): bool
    {
        return false;
    }
}
