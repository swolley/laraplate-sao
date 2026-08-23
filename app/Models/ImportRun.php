<?php

declare(strict_types=1);

namespace Modules\SAO\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\SAO\Database\Factories\ImportRunFactory;
use Modules\SAO\Enums\ImportRunStatus;
use Modules\SAO\Enums\ImportScope;
use Modules\SAO\Enums\SAOTables;
use Override;

/**
 * The persisted, resumable state of one tracker-import migration for a
 * (binding, scope) pair. It stores the driver's next-page cursor and the running
 * counts, so an import that spans thousands of pages — or that is interrupted by
 * a crash, a redeploy or a requeue — resumes from the exact page it stopped on
 * instead of restarting. The run flips to {@see ImportRunStatus::Completed} only
 * when the page walk is exhausted.
 *
 * @mixin \Eloquent
 *
 * @property int $id
 * @property int $binding_id
 * @property ImportScope $scope
 * @property ImportRunStatus $status
 * @property string|null $cursor
 * @property int $created_count
 * @property int $updated_count
 * @property int $filtered_count
 * @property int $skipped_count
 * @property int $pages
 * @property bool $truncated
 *
 * @mixin IdeHelperImportRun
 */
final class ImportRun extends Model
{
    /**
     * @var array<string, mixed>
     */
    #[Override]
    protected $attributes = [
        'status' => ImportRunStatus::Running->value,
        'created_count' => 0,
        'updated_count' => 0,
        'filtered_count' => 0,
        'skipped_count' => 0,
        'comment_count' => 0,
        'attachment_count' => 0,
        'pages' => 0,
        'truncated' => false,
    ];

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'binding_id',
        'scope',
        'status',
        'cursor',
        'created_count',
        'updated_count',
        'filtered_count',
        'skipped_count',
        'comment_count',
        'attachment_count',
        'pages',
        'truncated',
    ];

    /**
     * @var string
     */
    #[Override]
    protected $table = SAOTables::ImportRuns->value;

    /**
     * @return BelongsTo<ProjectBinding, $this>
     */
    public function binding(): BelongsTo
    {
        return $this->belongsTo(ProjectBinding::class, 'binding_id');
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $rules['create'] = array_merge($rules['create'], [
            'binding_id' => ['required', 'integer'],
            'scope' => ['required', 'string', 'in:' . implode(',', ImportScope::values())],
            'status' => ['required', 'string', 'in:' . implode(',', ImportRunStatus::values())],
        ]);

        return $rules;
    }

    /**
     * @return Factory<ImportRun>
     */
    protected static function newFactory(): Factory
    {
        return ImportRunFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'binding_id' => 'integer',
            'scope' => ImportScope::class,
            'status' => ImportRunStatus::class,
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'filtered_count' => 'integer',
            'skipped_count' => 'integer',
            'comment_count' => 'integer',
            'attachment_count' => 'integer',
            'pages' => 'integer',
            'truncated' => 'boolean',
        ];
    }
}
