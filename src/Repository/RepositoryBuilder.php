<?php

namespace Laravel\Foundation\Repository;

use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Foundation\Abstracts\AbstractModel;
use Laravel\Foundation\Abstracts\AbstractRepository;
use Laravel\Foundation\Cache\Builder;
use Laravel\Foundation\DTO\GetListRequestDTO;



/**
 * @method RepositoryBuilder whereHas($relation, Closure $callback = null, $operator = '>=', $count = 1)
 * @method RepositoryBuilder withCount($relations)
 * @method RepositoryBuilder where($column, $operator = null, $value = null, $boolean = 'and')
 * @method RepositoryBuilder when(mixed $condition, callable $closure)
 * @method RepositoryBuilder select(array $select)
 * @method RepositoryBuilder with(array $relations)
 * @method RepositoryBuilder whereIn($column, $values, $boolean = 'and', $not = false)
 * @method RepositoryBuilder whereNotIn($column, $values, $boolean = 'and')
 * @method RepositoryBuilder whereExists(Closure $callback, $boolean = 'and', $not = false)
 * @method RepositoryBuilder whereNotNull($columns, $boolean = 'and')
 * @method RepositoryBuilder orWhere($column, $operator = null, $value = null)
 * @method RepositoryBuilder whereRaw($sql, $bindings = [], $boolean = 'and')
 * @method Builder newQuery()
 * @method void dd()
 */
class RepositoryBuilder
{
    private AbstractRepository $repository;
    private Builder $builder;

    private int|bool $cacheFor = true;
    private array $cacheTags = [];
    private ?string $cacheKey = null;

    private ?int $limit = null;
    private int $offset = 1;


    public function __construct(private readonly string $repositoryNamespace)
    {
        $this->repository = app($this->repositoryNamespace);
        $this->builder = $this->repository->newQuery();
    }

    /**массив с фильтрами
     * @param array $filters
     * @return $this
     */
    public function filters(array $filters): static
    {
        foreach ($filters as $field => $value) {
            switch (true) {
                case is_array($value) || ($value instanceof Collection):
                    $this->builder->whereIn($field, $value);
                    break;
                case is_null($value):
                    $this->builder->whereNull($field);
                    break;
                case Str::endsWith($field, '@gte'):
                    $this->builder->where(Str::before($field, '@'), '>=', $value);
                    break;
                case Str::endsWith($field, '@lte'):
                    $this->builder->where(Str::before($field, '@'), '<=', $value);
                    break;
                case Str::endsWith($field, '@gt'):
                    $this->builder->where(Str::before($field, '@'), '>', $value);
                    break;
                case Str::endsWith($field, '@lt'):
                    $this->builder->where(Str::before($field, '@'), '<', $value);
                    break;
                default:
                    $this->builder->where($field, $value);
                    break;
            }
        }

        return $this;
    }

    /**настройка сортировки
     * @param array $orderBy
     * @return $this
     */
    public function orderBy(array $orderBy): static
    {
        foreach ($orderBy as $column => $direction) {
            $this->builder->orderBy($column, $direction);
        }
        return $this;
    }

    /**Поисковый запрос
     * @param string $query поисковый запрос
     * @param array $queryableFields список столбцов для поиска
     * @return $this
     */
    public function query(string $query, array $queryableFields): static
    {
        if (!empty($query) && !empty($queryableFields)) {
            $this->builder->where(function (Builder $builder) use ($query, $queryableFields) {
                foreach ($queryableFields as $queryableField) {
                    $builder->orWhere($queryableField, 'ilike', "%$query%");
                }
            });
        }

        return $this;
    }

    /**Число записей на странице
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**Номер страницы
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    /**список кастомных тегов для кэша
     * @param array $cacheTags
     * @return $this
     */
    public function cacheTags(array $cacheTags): static
    {
        $this->cacheTags = $cacheTags;
        return $this;
    }

    /**Установка кастомного ключа кэша
     * @param string|null $cacheKey
     * @return $this
     */
    public function cacheKey(?string $cacheKey): static
    {
        $this->cacheKey = $cacheKey;
        return $this;
    }

    /**Установка кастомного ключа кэша
     * @param int|bool $cacheFor
     * @return $this
     */
    public function cacheFor(int|bool $cacheFor): static
    {
        $this->cacheFor = $cacheFor;
        return $this;
    }

    /**Установка параметров фильтрации, пагинации, сортировки и поиска из DTO
     * @param GetListRequestDTO $dto
     * @param array $queryableFields
     * @return $this
     */
    public function fromGetListDto(GetListRequestDTO $dto, array $queryableFields = []): static
    {
        return $this
            ->limit($dto->perPage)
            ->offset($dto->page)
            ->filters($dto->filters)
            ->orderBy($dto->sort)
            ->query($dto->q, $queryableFields);
    }

    /**добавляет условие where с заданным замыканием
     * @param callable $closure
     * @return $this
     */
    public function closure(callable $closure): static
    {
        $this->builder->where($closure);
        return $this;
    }

    /**Возвращает все найденные записи из БД
     * @return Collection
     */
    public function get(): Collection
    {
        return $this->toQuery()->get();
    }

    /**Возвращает пагинатор с выбранной страницей
     * @return LengthAwarePaginator
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->toQuery()->paginate(perPage: $this->limit, page: $this->offset);
    }

    /**Возвращает первую найденную запись или null
     * @return AbstractModel|null
     */
    public function find(): ?AbstractModel
    {
        return $this->toQuery()->take(1)->get()->first();
    }

    /**проверка существования хотя бы одной модели
     * @return bool
     */
    public function exists(): bool
    {
        return $this->toQuery()->exists();
    }

    /**Количество элементов в текущем запросе
     * @return int
     */
    public function count(): int
    {
        return $this->toQuery()->count();
    }


    /**Возвращает объект \Laravel\Foundation\Cache\Builder с примененными правилами
     * @return Builder
     */
    public function toQuery(): Builder
    {
        $this->preparePagination();
        $this->prepareCache();
        return $this->builder;
    }

    private function prepareCache(): void
    {
        if ($this->cacheFor !== false) {
            $cacheFor = $this->cacheFor === true ? config('cache.ttl') : $this->cacheFor;
            $this->builder->cacheFor($cacheFor);
            $this->builder->cacheTags($this->cacheTags);
            if ($this->cacheKey) {
                $this->builder->cacheKey($this->cacheKey);
            }
        }
    }

    private function preparePagination(): void
    {
        $this->builder->when($this->limit, function ($builder) {
            $builder->forPage($this->offset, $this->limit);
        });
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$arguments);
        }

        $this->builder->{$name}(...$arguments);

        return $this;
    }

}
