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
 * The `logs` driver for Elasticsearch/OpenSearch alerting (Kibana rules or
 * Watcher), over a webhook action. The action body is template-defined; this
 * reads the conventional `message`/`reason` fields, or an `alerts` array when
 * present. No native grouping key, so `carriesNativeGroupKey()` is false.
 * Deliveries carry a shared token the action is configured to send in a header.
 */
final readonly class ElasticDriver implements DriverInterface, LogsCapability
{
    use LogsDriverBoilerplate;

    #[Override]
    public function key(): string
    {
        return 'elastic';
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('secret', 'string', 'Shared token the alerting action sends in the webhook header', required: true, secret: true),
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Override]
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool
    {
        return $this->tokenVerified($context, $headers, 'X-Elastic-Token');
    }

    #[Override]
    public function unpack(BindingContext $context, string $payload): Page
    {
        $decoded = $this->decode($payload);

        /** @var list<array<string, mixed>> $alerts */
        $alerts = is_array($decoded['alerts'] ?? null) ? array_values(array_filter($decoded['alerts'], 'is_array')) : [];
        $rows = $alerts === [] ? [$decoded] : $alerts;

        $items = [];

        foreach ($rows as $row) {
            $message = $this->stringOrNull($row['message'] ?? $row['reason'] ?? $row['rule_name'] ?? null);

            if ($message === null) {
                continue;
            }

            $items[] = [
                'source' => $this->key(),
                'message' => $message,
                'level' => $this->stringOrNull($row['level'] ?? $row['severity'] ?? null),
                'environment' => $this->stringOrNull($row['environment'] ?? null),
                'occurred_at' => $this->stringOrNull($row['timestamp'] ?? $row['@timestamp'] ?? null),
                'raw' => $row,
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
