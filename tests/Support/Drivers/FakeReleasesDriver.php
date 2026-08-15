<?php

declare(strict_types=1);

namespace Modules\SAO\Tests\Support\Drivers;

use Modules\SAO\Drivers\Contracts\DriverInterface;
use Modules\SAO\Drivers\Contracts\ReleasesCapability;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionContext;
use Modules\SAO\Drivers\Support\DriverConfigurationSchema;
use Modules\SAO\Drivers\Support\HealthCheckResult;
use Modules\SAO\Drivers\Support\Page;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\IngestMode;
use Override;

/**
 * A second in-test driver exposing a different capability, used to prove the
 * registry filters by capability and accepts drivers registered from outside.
 */
final class FakeReleasesDriver implements DriverInterface, ReleasesCapability
{
    #[Override]
    public function key(): string
    {
        return 'fake-releases';
    }

    /**
     * @return list<Capability>
     */
    #[Override]
    public function capabilities(): array
    {
        return [Capability::Releases];
    }

    /**
     * @return list<IngestMode>
     */
    #[Override]
    public function ingestModes(): array
    {
        return [IngestMode::Pull];
    }

    #[Override]
    public function configurationSchema(): DriverConfigurationSchema
    {
        return new DriverConfigurationSchema([]);
    }

    #[Override]
    public function healthCheck(ConnectionContext $context): HealthCheckResult
    {
        return HealthCheckResult::healthy();
    }

    #[Override]
    public function tags(BindingContext $context, ?string $cursor = null): Page
    {
        return new Page([['tag' => 'v1.0.0'], ['tag' => 'v1.0.1']], nextCursor: null);
    }

    #[Override]
    public function firstTagContaining(BindingContext $context, string $commitSha): ?string
    {
        return null;
    }
}
