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
 * The `logs` driver for Rollbar, over its webhook. Rollbar groups occurrences
 * under an "item" with a stable counter, so `carriesNativeGroupKey()` is true
 * and the event is namespaced `rollbar:<counter>`. Deliveries carry a shared
 * token the webhook is configured to send in a header.
 */
final readonly class RollbarDriver implements DriverInterface, LogsCapability
{
    use LogsDriverBoilerplate;

    #[Override]
    public function key(): string
    {
        return 'rollbar';
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('secret', 'string', 'Shared token Rollbar sends in the webhook header', required: true, secret: true),
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Override]
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool
    {
        return $this->tokenVerified($context, $headers, 'X-Rollbar-Token');
    }

    #[Override]
    public function unpack(BindingContext $context, string $payload): Page
    {
        $decoded = $this->decode($payload);

        /** @var array<string, mixed> $data */
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

        /** @var array<string, mixed> $item */
        $item = is_array($data['item'] ?? null) ? $data['item'] : [];

        // The counter is the stable, human-facing item identity; the raw id is
        // the fallback.
        $nativeKey = (string) ($item['counter'] ?? $item['id'] ?? '');

        if ($nativeKey === '') {
            return new Page([]);
        }

        return new Page([[
            'native_key' => $nativeKey,
            'source' => $this->key(),
            'message' => (string) ($item['title'] ?? ''),
            'level' => $this->stringOrNull($item['level'] ?? null),
            'environment' => $this->stringOrNull($item['environment'] ?? null),
            'url' => $this->stringOrNull($item['url'] ?? null),
            'raw' => $decoded,
        ]]);
    }

    #[Override]
    public function carriesNativeGroupKey(): bool
    {
        return true;
    }
}
