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
 * The `logs` driver for Datadog, over a custom webhook. Datadog's monitor
 * payload is template-defined; this reads the conventional `title`/`body`
 * fields. Datadog has no per-error native grouping key for our purposes, so
 * `carriesNativeGroupKey()` is false and SAO fingerprints each event.
 * Deliveries carry a shared token the webhook is configured to send in a header.
 */
final readonly class DatadogDriver implements DriverInterface, LogsCapability
{
    use LogsDriverBoilerplate;

    #[Override]
    public function key(): string
    {
        return 'datadog';
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('secret', 'string', 'Shared token Datadog sends in the webhook header', required: true, secret: true),
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Override]
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool
    {
        return $this->tokenVerified($context, $headers, 'X-Datadog-Token');
    }

    #[Override]
    public function unpack(BindingContext $context, string $payload): Page
    {
        $decoded = $this->decode($payload);

        $message = $this->stringOrNull($decoded['title'] ?? $decoded['body'] ?? null);

        if ($message === null) {
            return new Page([]);
        }

        return new Page([[
            'source' => $this->key(),
            'message' => $message,
            'level' => $this->stringOrNull($decoded['alert_type'] ?? $decoded['priority'] ?? null),
            'environment' => $this->stringOrNull($decoded['env'] ?? null),
            'url' => $this->stringOrNull($decoded['link'] ?? $decoded['url'] ?? null),
            'occurred_at' => $this->stringOrNull($decoded['date'] ?? null),
            'raw' => $decoded,
        ]]);
    }

    #[Override]
    public function carriesNativeGroupKey(): bool
    {
        return false;
    }
}
