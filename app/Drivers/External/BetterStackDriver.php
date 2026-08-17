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
 * The `logs` driver for Better Stack (Logtail/Uptime), over its incident
 * webhook. The payload is a JSON:API incident resource; this reads its
 * `data.attributes.name`/`cause`. No per-error native grouping key, so
 * `carriesNativeGroupKey()` is false. Deliveries carry a shared token the
 * webhook integration is configured to send in a header.
 */
final readonly class BetterStackDriver implements DriverInterface, LogsCapability
{
    use LogsDriverBoilerplate;

    #[Override]
    public function key(): string
    {
        return 'betterstack';
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('secret', 'string', 'Shared token Better Stack sends in the webhook header', required: true, secret: true),
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Override]
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool
    {
        return $this->tokenVerified($context, $headers, 'X-BetterStack-Token');
    }

    #[Override]
    public function unpack(BindingContext $context, string $payload): Page
    {
        $decoded = $this->decode($payload);

        /** @var array<string, mixed> $data */
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

        /** @var array<string, mixed> $attributes */
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        $message = $this->stringOrNull($attributes['name'] ?? $attributes['cause'] ?? null);

        if ($message === null) {
            return new Page([]);
        }

        return new Page([[
            'source' => $this->key(),
            'message' => $message,
            'environment' => $this->stringOrNull($attributes['environment'] ?? null),
            'url' => $this->stringOrNull($attributes['url'] ?? null),
            'occurred_at' => $this->stringOrNull($attributes['started_at'] ?? null),
            'raw' => $decoded,
        ]]);
    }

    #[Override]
    public function carriesNativeGroupKey(): bool
    {
        return false;
    }
}
