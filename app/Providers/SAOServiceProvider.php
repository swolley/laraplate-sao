<?php

declare(strict_types=1);

namespace Modules\SAO\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Exceptions\ConfigurationException;
use Modules\Core\Logging\Fingerprint\Fingerprinter;
use Modules\Core\Logging\Fingerprint\FingerprintNormalizer;
use Modules\Core\Overrides\ModuleServiceProvider;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\SAO\Contracts\SuggestionPhraser;
use Modules\SAO\Contracts\SuggestionTextGenerator;
use Modules\SAO\Drivers\DriverRegistry;
use Modules\SAO\Ingest\PipelineContext;
use Modules\SAO\Models\Connection;
use Modules\SAO\Models\IngestEvent;
use Modules\SAO\Models\OwnershipSuggestion;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Policies\SaoModelPolicy;
use Modules\SAO\Services\AiSuggestionPhraser;
use Modules\SAO\Services\DomainActions\SaoDomainActionRegistrar;
use Modules\SAO\Services\EventTextGenerator;
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

        $this->app->singleton(DriverRegistry::class, static function ($app): DriverRegistry {
            $registry = new DriverRegistry;

            /** @var list<class-string> $registered */
            $registered = (array) config('sao.drivers.registered', []);

            foreach ($registered as $driver) {
                $registry->register($app->make($driver));
            }

            return $registry;
        });

        // The shared fingerprinter is built from Core's default rule chain so
        // the payload path and the in-process resolver hash identically.
        $this->app->bind(
            Fingerprinter::class,
            static fn (): Fingerprinter => new Fingerprinter(FingerprintNormalizer::default()),
        );

        // The pipeline-origin marker must be shared process-wide.
        $this->app->singleton(PipelineContext::class);

        // Optional AI phrasing without an AI dependency: the phraser asks for a
        // rewrite through Core's AiTextGenerationRequested event and falls back
        // to its deterministic text when nothing answers. It never invents the
        // owner (D14).
        $this->app->bind(SuggestionTextGenerator::class, EventTextGenerator::class);
        $this->app->bind(SuggestionPhraser::class, AiSuggestionPhraser::class);
    }

    #[Override]
    public function boot(): void
    {
        parent::boot();

        foreach ($this->policyModels() as $model) {
            Gate::policy($model, SaoModelPolicy::class);
        }

        resolve(SaoDomainActionRegistrar::class)->register(resolve(DomainActionRegistry::class));
    }

    /**
     * SAO models that expose domain actions through {@see SaoModelPolicy}.
     *
     * @return list<class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private function policyModels(): array
    {
        return [
            Ticket::class,
            OwnershipSuggestion::class,
            Connection::class,
            IngestEvent::class,
        ];
    }

    /**
     * Schedule the inbound issue poll, the signal auto-open and the connection
     * health probe. Each is gated by its own config flag so an operator can keep
     * it manual-only, and each is harmless when on with nothing configured (a
     * binding must opt into an inbound sync direction to be polled; a signal must
     * reach the threshold to open a ticket; the health probe simply finds no
     * connections).
     */
    #[Override]
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);

            if (config('sao.sync.enabled', true)) {
                $schedule->command('sao:sync:issues')
                    ->cron((string) config('sao.sync.cron', '0 * * * *'))
                    ->withoutOverlapping();
            }

            if (config('sao.signals.auto_open.enabled', false)) {
                $schedule->command('sao:signals:auto-open')
                    ->everyFiveMinutes()
                    ->withoutOverlapping();
            }

            if (config('sao.health.enabled', false)) {
                $schedule->command('sao:connection:health')
                    ->cron((string) config('sao.health.cron', '*/15 * * * *'))
                    ->withoutOverlapping();
            }
        });
    }
}
