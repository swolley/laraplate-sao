<?php

declare(strict_types=1);

namespace Modules\SAO\ApplicationContent;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderInterface;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentResult;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\SAO\Models\Ticket;
use Modules\SAO\Services\TicketQueryService;
use Override;
use Throwable;

final class SaoApplicationContentRetrievalProvider implements ApplicationContentRetrievalProviderInterface
{
    public function __construct(
        private readonly AdvancedSearchService $search,
        private readonly TicketQueryService $tickets,
        private readonly QueryBuilder $queryBuilder,
        private readonly SaoTicketEvidenceProjector $projector,
    ) {}

    #[Override]
    public function descriptor(): ApplicationContentSourceDescriptor
    {
        return new ApplicationContentSourceDescriptor(
            source: 'sao.tickets',
            module: 'sao',
            entity: 'tickets',
            supportedLocales: [(string) config('app.locale', 'en')],
            capabilities: ['hybrid', 'lexical', 'semantic'],
            intentCategories: ['application_content', 'sao', 'ticket', 'issue', 'task'],
        );
    }

    #[Override]
    public function retrieve(
        ApplicationContentQuery $query,
        ApplicationContentAuthorization $authorization,
    ): ApplicationContentResult {
        $connection = (new Ticket)->getConnectionName() ?: 'default';
        $window = min(50, $query->limit + 1);

        $search_result = $this->advancedSearch($query, $authorization->filters);
        $ranked = $this->rankedHits($search_result, $window, $connection);

        if ($ranked === []) {
            $search_result = $this->lexicalFallback($query, $authorization);
            $ranked = $this->rankedHits($search_result, $window, $connection);
        }

        $strategy = $this->strategy($search_result);
        $records = $this->rehydrate(array_keys($ranked), $authorization);
        $hits = [];

        foreach ($ranked as $record_id => $score) {
            $ticket = $records[$record_id] ?? null;

            if (! $ticket instanceof Ticket) {
                continue;
            }

            $hit = $this->projector->project($ticket, $query->locale, $strategy, $score);

            if ($hit !== null) {
                $hits[] = $hit;
            }
        }

        return new ApplicationContentResult(
            source: 'sao.tickets',
            hits: array_slice($hits, 0, $query->limit),
            strategy: $strategy,
            truncated: $query->limit < count($hits),
        );
    }

    private function advancedSearch(ApplicationContentQuery $query, ?FiltersGroup $filters): AdvancedSearchResult
    {
        try {
            return $this->search->search(new Ticket, $query->query, 1, min(50, $query->limit + 1), $filters);
        } catch (Throwable) {
            return AdvancedSearchResult::empty(1, min(50, $query->limit + 1), ['degraded' => ['lexical_fallback']]);
        }
    }

    private function lexicalFallback(
        ApplicationContentQuery $query,
        ApplicationContentAuthorization $authorization,
    ): AdvancedSearchResult {
        $needle = '%' . addcslashes($query->query, '\\%_') . '%';
        $database_query = $this->authorizedQuery($authorization)
            ->where(function (Builder $q) use ($needle): void {
                $q->where('title', 'like', $needle)->orWhere('description', 'like', $needle);
            });

        $ids = $database_query
            ->orderBy((new Ticket)->qualifyColumn('id'))
            ->limit(min(50, $query->limit + 1))
            ->pluck((new Ticket)->qualifyColumn('id'))
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        $hits = [];

        foreach ($ids as $position => $id) {
            $hits[] = [
                'id' => $id,
                'score' => round(1 / ($position + 1), 6),
                'source' => ['connection' => (new Ticket)->getConnectionName() ?: 'default'],
            ];
        }

        return new AdvancedSearchResult(
            hits: $hits,
            total: count($hits),
            page: 1,
            perPage: min(50, $query->limit + 1),
            totalPages: $hits === [] ? 0 : 1,
            meta: ['strategies' => ['keyword'], 'degraded' => ['lexical_fallback']],
        );
    }

    /**
     * @param  list<string>  $recordIds
     * @return array<string, Ticket>
     */
    private function rehydrate(array $recordIds, ApplicationContentAuthorization $authorization): array
    {
        if ($recordIds === []) {
            return [];
        }

        return $this->authorizedQuery($authorization)
            ->whereKey($recordIds)
            ->with(['status', 'type', 'project', 'assignee'])
            ->get()
            ->mapWithKeys(static fn (Ticket $ticket): array => [(string) $ticket->getKey() => $ticket])
            ->all();
    }

    /**
     * @return Builder<Ticket>
     */
    private function authorizedQuery(ApplicationContentAuthorization $authorization): Builder
    {
        $query = $this->tickets->visible();

        if ($authorization->filters instanceof FiltersGroup) {
            $this->queryBuilder->applyFilters($query, $authorization->filters);
        }

        return $query;
    }

    /**
     * @return array<string, float>
     */
    private function rankedHits(AdvancedSearchResult $result, int $limit, string $connection): array
    {
        $ranked = [];

        foreach ($result->hits as $hit) {
            $id = $hit['id'] ?? null;
            $score = $hit['score'] ?? null;
            $source = is_array($hit['source'] ?? null) ? $hit['source'] : [];

            if (! is_string($id)
                || $id === ''
                || ($source['connection'] ?? null) !== $connection
                || isset($ranked[$id])) {
                continue;
            }

            $ranked[$id] = is_numeric($score)
                ? max(0.0, min(1.0, (float) $score))
                : 0.0;

            if ($limit <= count($ranked)) {
                break;
            }
        }

        return $ranked;
    }

    private function strategy(AdvancedSearchResult $result): string
    {
        $strategies = is_array($result->meta['strategies'] ?? null)
            ? $result->meta['strategies']
            : [];

        if (in_array('hybrid', $strategies, true)
            || (in_array('keyword', $strategies, true) && in_array('vector', $strategies, true))) {
            return 'hybrid';
        }

        return in_array('vector', $strategies, true) ? 'semantic' : 'lexical';
    }
}
