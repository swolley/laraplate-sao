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
 * The `logs` driver for Bugsnag, over its data-forwarding webhook. Bugsnag
 * assigns each error a stable `errorId`, so `carriesNativeGroupKey()` is true
 * and the event is namespaced `bugsnag:<errorId>`. Deliveries carry a shared
 * token the integration is configured to send in a header.
 */
final readonly class BugsnagDriver implements DriverInterface, LogsCapability
{
    use LogsDriverBoilerplate;

    #[Override]
    public function key(): string
    {
        return 'bugsnag';
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('secret', 'string', 'Shared token Bugsnag sends in the webhook header', required: true, secret: true),
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Override]
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool
    {
        return $this->tokenVerified($context, $headers, 'X-Bugsnag-Token');
    }

    #[Override]
    public function unpack(BindingContext $context, string $payload): Page
    {
        $decoded = $this->decode($payload);

        /** @var array<string, mixed> $error */
        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];

        $nativeKey = (string) ($error['errorId'] ?? '');

        if ($nativeKey === '') {
            return new Page([]);
        }

        return new Page([[
            'native_key' => $nativeKey,
            'source' => $this->key(),
            'message' => (string) ($error['message'] ?? $error['exceptionClass'] ?? ''),
            'class' => $this->stringOrNull($error['exceptionClass'] ?? null),
            'level' => $this->stringOrNull($error['severity'] ?? null),
            'environment' => $this->stringOrNull($error['releaseStage'] ?? null),
            'url' => $this->stringOrNull($error['url'] ?? null),
            'raw' => $decoded,
        ]]);
    }

    #[Override]
    public function carriesNativeGroupKey(): bool
    {
        return true;
    }
}
