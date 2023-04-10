<?php


namespace Laravel\Foundation\Presenters;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Laravel\Foundation\Abstracts\AbstractPresenter;

class PaginatedDataPresenter extends AbstractPresenter
{
    public function __construct(
        LengthAwarePaginator $awarePaginator,
        protected array|Collection|null $aggregation = null,
        private readonly string|null $resourceNamespace = null,
    ) {
        parent::__construct($awarePaginator);
    }

    protected final function resolve()
    {
        /** @var LengthAwarePaginator $resource */
        $resource = $this->resource;
        return [
            'error' => false,
            'content' => [
                'data' => $resource->getCollection()->transform($this->mapPaginationBody()),
                'meta' => [
                    'current_page' => $resource->currentPage(),
                    'per_page' => $resource->perPage(),
                    'last_page' => $resource->lastPage(),
                    'total' => $resource->total(),
                ],
                'filter' => $this->aggregationsToArray(),
            ],
        ];
    }

    /**
     * Вернет замыкание. которые поподет в метод transform от класса пагинации
     * @return callable
     */
    protected function mapPaginationBody(): callable
    {
        return fn($value) => $this->resourceNamespace !== null
            ? new $this->resourceNamespace($value)
            : $this->bodyToArray($value);
    }

    protected function bodyToArray($item)
    {
        return $item;
    }

    protected function aggregationsToArray(): array
    {
        if (empty($this->aggregation)) {
            return [];
        }

        $count = collect($this->aggregation)
            ->get('_id')
            ?->getAttribute('value');

        return [
            ...$this->aggregation,
            'count' => !is_null($count) ? $count : ($this->aggregation['count'] ?: 0),
        ];
    }
}
