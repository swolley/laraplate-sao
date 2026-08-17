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
 * The `logs` driver for Honeybadger, over its webhook. Honeybadger groups
 * occurrences under a "fault" with a stable id, so `carriesNativeGroupKey()` is
 * true and the event is namespaced `honeybadger:<fault id>`. Deliveries carry a
 * shared token the webhook is configured to send in a header.
 */
final readonly class HoneybadgerDriver implements DriverInterface, LogsCapability
{
    use LogsDriverBoilerplate;

    #[Override]
    public function key(): string
    {
        return 'honeybadger';
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('secret', 'string', 'Shared token Honeybadger sends in the webhook header', required: true, secret: true),
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Override]
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool
    {
        return $this->tokenVerified($context, $headers, 'X-Honeybadger-Token');
    }

    #[Override]
    public function unpack(BindingContext $context, string $payload): Page
    {
        $decoded = $this->decode($payload);

        /** @var array<string, mixed> $fault */
        $fault = is_array($decoded['fault'] ?? null) ? $decoded['fault'] : [];

        $nativeKey = (string) ($fault['id'] ?? '');

        if ($nativeKey === '') {
            return new Page([]);
        }

        return new Page([[
            'native_key' => $nativeKey,
            'source' => $this->key(),
            'message' => (string) ($fault['message'] ?? $fault['klass'] ?? ''),
            'class' => $this->stringOrNull($fault['klass'] ?? null),
            'environment' => $this->stringOrNull($fault['environment'] ?? null),
            'url' => $this->stringOrNull($fault['url'] ?? null),
            'raw' => $decoded,
        ]]);
    }

    #[Override]
    public function carriesNativeGroupKey(): bool
    {
        return true;
    }
}
