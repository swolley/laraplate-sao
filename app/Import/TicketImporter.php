<?php

declare(strict_types=1);

namespace Modules\SAO\Import;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Import\Contracts\EntityImporterInterface;
use Modules\Core\Import\Enums\ImportRowOutcome;
use Modules\Core\Import\Exceptions\RowImportException;
use Modules\Core\Import\Support\ImportRowContext;
use Modules\Core\Import\Support\RecordOriginRegistry;
use Modules\Core\Import\ValueObjects\ExternalRecordIdentity;
use Modules\Core\Import\ValueObjects\ImportField;
use Modules\SAO\Data\ChangeContext;
use Modules\SAO\Models\Project;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketCreationService;
use Override;

/**
 * Imports tickets into SAO from a spreadsheet/CSV/JSON dump — the concrete
 * "file-dump" path the tracker-migration spec deferred, now just this generic
 * framework with a `sao.ticket` entity registered.
 *
 * A row names its project by key prefix; the ticket opens through
 * {@see TicketCreationService} at the project's default type
 * and initial workflow status. When a row carries an `external_id`, it is the
 * dedupe identity (stored in `core_record_origins`), so re-importing the same dump
 * updates rather than duplicates; without one, each row is a fresh ticket.
 */
final readonly class TicketImporter implements EntityImporterInterface
{
    public function __construct(
        private TicketCreationService $creation,
        private RecordOriginRegistry $origins,
    ) {}

    #[Override]
    public function key(): string
    {
        return 'sao.ticket';
    }

    #[Override]
    public function label(): string
    {
        return 'Tickets';
    }

    /**
     * @return list<ImportField>
     */
    #[Override]
    public function fields(): array
    {
        return [
            new ImportField('project_key', 'Project key', required: true, aliases: ['project', 'key']),
            new ImportField('title', 'Title', required: true, aliases: ['summary', 'subject']),
            new ImportField('description', 'Description', aliases: ['body']),
            new ImportField('external_id', 'External id', aliases: ['id', 'ticket_id', 'remote_id']),
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    #[Override]
    public function import(array $row, ImportRowContext $context): ImportRowOutcome
    {
        $projectKey = mb_strtoupper(mb_trim($row['project_key'] ?? ''));
        $title = mb_trim($row['title'] ?? '');
        $externalId = mb_trim($row['external_id'] ?? '');
        $description = ($row['description'] ?? '') === '' ? null : $row['description'];

        $validator = Validator::make(
            ['project_key' => $projectKey, 'title' => $title],
            ['project_key' => ['required'], 'title' => ['required', 'string', 'max:255']],
        );

        if ($validator->fails()) {
            throw RowImportException::withErrors($validator->errors()->messages());
        }

        $project = Project::query()->where('key_prefix', $projectKey)->first();

        if (! $project instanceof Project) {
            throw RowImportException::withErrors(['project_key' => ["Unknown project [{$projectKey}]."]]);
        }

        $type = $project->defaultTicketType();

        if ($type === null) {
            throw RowImportException::withErrors(['project_key' => ["Project [{$projectKey}] has no default ticket type."]]);
        }

        $existingId = $externalId === ''
            ? null
            : $this->origins->referableId(new Ticket, $context->sourceKey(), $externalId);

        $existing = $existingId === null ? null : Ticket::query()->find($existingId);

        if ($existing instanceof Ticket) {
            $existing->fill(['title' => $title, 'description' => $description])->save();
            $ticket = $existing;
            $outcome = ImportRowOutcome::Updated;
        } else {
            $ticket = $this->creation->open(
                $project,
                $type,
                ['title' => $title, 'description' => $description],
                ChangeContext::forAutomation('import'),
            );
            $outcome = ImportRowOutcome::Created;
        }

        $this->origins->register(
            $ticket,
            new ExternalRecordIdentity(
                $context->sourceKey(),
                $externalId === '' ? null : $externalId,
                hash('sha256', (string) json_encode($row)),
            ),
            $context->session->original_filename,
        );

        return $outcome;
    }
}
