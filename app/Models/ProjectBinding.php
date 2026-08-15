<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\ProjectBindingFactory;
use Modules\SAO\Drivers\Support\BindingContext;
use Modules\SAO\Drivers\Support\ConnectionCredentialResolver;
use Modules\SAO\Enums\Capability;
use Modules\SAO\Enums\SAOTables;
use Modules\SAO\Enums\SyncDirection;
use Modules\SAO\Exceptions\UnsupportedCapabilityException;
use Override;

/**
 * Binds a project to one capability of one connection, with the remote object
 * it targets and the binding-scoped configuration (sync direction, status and
 * priority maps). Multiple bindings of the same family are allowed.
 *
 * @property int $project_id
 * @property int $connection_id
 * @property Capability $capability
 * @property ?string $remote_identifier
 * @property SyncDirection $sync_direction
 * @property array<string, string> $status_map
 * @property array<string, string> $priority_map
 * @property array<string, mixed> $config
 *
 * @mixin \Eloquent
 */
final class ProjectBinding extends Model
{
    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'project_id',
        'connection_id',
        'capability',
        'remote_identifier',
        'sync_direction',
        'status_map',
        'priority_map',
        'config',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::ProjectBindings->value;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Named `remoteConnection` rather than `connection` to avoid colliding with
     * Eloquent's built-in `$connection` (database connection name) property.
     *
     * @return BelongsTo<Connection, $this>
     */
    public function remoteConnection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    /**
     * Build the resolved view a capability call operates on: the connection
     * (base URL + resolved credentials) plus this binding's remote identifier,
     * config and maps.
     */
    public function bindingContext(ConnectionCredentialResolver $resolver): BindingContext
    {
        $connection = $this->remoteConnection;

        return new BindingContext(
            $connection->connectionContext($resolver->resolve($connection)),
            remoteIdentifier: $this->remote_identifier,
            config: $this->config ?? [],
            statusMap: $this->status_map ?? [],
            priorityMap: $this->priority_map ?? [],
        );
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $shared = [
            'remote_identifier' => ['nullable', 'string', 'max:255'],
            'sync_direction' => ['sometimes', 'string', SyncDirection::validationRule()],
            'status_map' => ['nullable', 'json'],
            'priority_map' => ['nullable', 'json'],
            'config' => ['nullable', 'json'],
        ];

        $rules['create'] = array_merge($rules['create'], $shared, [
            'project_id' => ['required', 'integer', 'exists:' . SAOTables::Projects->value . ',id'],
            'connection_id' => ['required', 'integer', 'exists:' . SAOTables::Connections->value . ',id'],
            'capability' => ['required', 'string', Capability::validationRule()],
        ]);

        $rules['update'] = array_merge($rules['update'], $shared);

        return $rules;
    }

    #[Override]
    protected static function booted(): void
    {
        parent::booted();

        self::saving(static function (ProjectBinding $binding): void {
            $connection = $binding->remoteConnection;

            if (! in_array($binding->capability, $connection->capabilities->all(), true)) {
                throw UnsupportedCapabilityException::for($connection->driver_key, $binding->capability);
            }
        });
    }

    /**
     * @return Factory<ProjectBinding>
     */
    protected static function newFactory(): Factory
    {
        return ProjectBindingFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'capability' => Capability::class,
            'sync_direction' => SyncDirection::class,
            'status_map' => 'array',
            'priority_map' => 'array',
            'config' => 'array',
        ];
    }
}
