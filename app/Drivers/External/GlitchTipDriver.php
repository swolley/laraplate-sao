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
 * The `logs` driver for GlitchTip (a Sentry-compatible error tracker), over its
 * webhook. Like Sentry it carries a stable native issue id, so
 * `carriesNativeGroupKey()` is true and the event is namespaced `glitchtip:<id>`
 * by the `GroupKeyResolver`. Deliveries carry a shared token the instance is
 * configured to send in a header.
 */
final readonly class GlitchTipDriver implements DriverInterface, LogsCapability
{
    use LogsDriverBoilerplate;

    #[Override]
    public function key(): string
    {
        return 'glitchtip';
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([
            new ConfigurationField('secret', 'string', 'Shared token GlitchTip sends in the webhook header', required: true, secret: true),
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[Override]
    public function verifySignature(BindingContext $context, string $payload, array $headers): bool
    {
        return $this->tokenVerified($context, $headers, 'X-GlitchTip-Token');
    }

    #[Override]
    public function unpack(BindingContext $context, string $payload): Page
    {
        $decoded = $this->decode($payload);

        /** @var array<string, mixed> $data */
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

        /** @var array<string, mixed> $issue */
        $issue = is_array($data['issue'] ?? null) ? $data['issue'] : [];

        /** @var array<string, mixed> $event */
        $event = is_array($data['event'] ?? null) ? $data['event'] : [];

        $nativeKey = (string) ($issue['id'] ?? '');

        if ($nativeKey === '') {
            return new Page([]);
        }

        return new Page([[
            'native_key' => $nativeKey,
            'source' => $this->key(),
            'message' => (string) ($issue['title'] ?? $event['title'] ?? ''),
            'level' => $this->stringOrNull($issue['level'] ?? $event['level'] ?? null),
            'environment' => $this->stringOrNull($event['environment'] ?? null),
            'culprit' => $this->stringOrNull($issue['culprit'] ?? null),
            'url' => $this->stringOrNull($issue['web_url'] ?? $issue['url'] ?? null),
            'occurred_at' => $this->stringOrNull($event['datetime'] ?? $issue['lastSeen'] ?? null),
            'raw' => $decoded,
        ]]);
    }

    #[Override]
    public function carriesNativeGroupKey(): bool
    {
        return true;
    }
}
