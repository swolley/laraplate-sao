<?php

declare(strict_types=1);

namespace Modules\SAO\Providers;

use Modules\Core\Overrides\RouteServiceProvider as ServiceProvider;
use Override;

final class RouteServiceProvider extends ServiceProvider
{
    #[Override]
    protected string $name = 'SAO';
}
