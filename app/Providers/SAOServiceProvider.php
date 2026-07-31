<?php

declare(strict_types=1);

namespace Modules\SAO\Providers;

use Modules\Core\Exceptions\ConfigurationException;
use Modules\Core\Overrides\ModuleServiceProvider;
use Nwidart\Modules\Facades\Module;
use Override;

final class SAOServiceProvider extends ModuleServiceProvider
{
    #[Override]
    protected string $name = 'SAO';

    #[Override]
    protected string $nameLower = 'sao';

    /**
     * Provider classes to register.
     *
     * @var array<int, class-string>
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    #[Override]
    public function register(): void
    {
        throw_unless(Module::find('Core'), ConfigurationException::class, 'Core is required and must be enabled');

        parent::register();
    }
}
