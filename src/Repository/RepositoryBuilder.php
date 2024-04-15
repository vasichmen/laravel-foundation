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
 * @method RepositoryBuilder fromRaw($expression, $bindings = [])
 * @method RepositoryBuilder selectRaw($expression, array $bindings = [])
 * @method Builder newQuery()
 * @method void dd()
 * @method void ddRawSql()
 * @method void dump()
 * @method void dumpRawSql()
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


    /**Создание нового экземпляра билдера. Устарел, вместо него используйте ::make()
     * @param string|null $repositoryNamespace
     * @deprecated
     */
    public function __construct(private readonly ?string $repositoryNamespace)
    {
        if ($this->repositoryNamespace) {
            $this->repository = app($this->repositoryNamespace);
            $this->builder = $this->repository->newQuery();
        }
    }

    /**Создание билдера с конкретным репозиотрием и моделью
     * @param AbstractRepository|string $repository
     * @param AbstractModel $model
     * @return RepositoryBuilder
     */
    public static function make(AbstractRepository|string $repository, ?AbstractModel $model): RepositoryBuilder
    {
        if (is_string($repository)) {
            $repository = new $repository(null);
            $repository->setModel($model);
        }
        $builder = new RepositoryBuilder(null);
        $builder->setRepository($repository);
        return $builder;
    }

    public function setRepository(AbstractRepository $repository): RepositoryBuilder
    {
        $this->repository = $repository;
        $this->builder = $this->repository->getModelInstance()->newQuery();
        return $this;
    }

    /**массив с фильтрами
     * @param array $filters
     * @return $this
     */
    public function filters(array $filters,): static
    {
        foreach ($filters as $field => $value) {
            $this->setFilter($this->builder, $field, $value);
        }

        return $this;
    }

    /**Установка фильтра по заданному полю в билдере с учетом модификаторов полей для операторов
     * @param Builder $builder
     * @param string $field код поля + оператор (если есть)
     * @param $value
     * @return void
     */
    private function setFilter(Builder $builder, string $field, $value): void
    {
        switch (true) {
            case Str::contains($field, '.'):
                $this->setRelationFilter($builder, $field, $value);
                break;
            case is_array($value) || ($value instanceof Collection):
                $builder->whereIn($field, $value);
                break;
            case is_null($value):
                $builder->whereNull($field);
                break;
            case Str::endsWith($field, '@gte'):
                $builder->where(Str::before($field, '@'), '>=', $value);
                break;
            case Str::endsWith($field, '@lte'):
                $builder->where(Str::before($field, '@'), '<=', $value);
                break;
            case Str::endsWith($field, '@gt'):
                $builder->where(Str::before($field, '@'), '>', $value);
                break;
            case Str::endsWith($field, '@lt'):
                $builder->where(Str::before($field, '@'), '<', $value);
                break;
            case Str::endsWith($field, '@?'):
                $builder->where(Str::before($field, '@'), '?', $value);
                break;
            case Str::endsWith($field, '@!'):
                $builder->whereNot(Str::before($field, '@'), $value);
                break;
            case Str::endsWith($field, '@like'):
                $builder->where(Str::before($field, '@'), 'like', "%$value%");
                break;
            case Str::endsWith($field, '@ilike'):
                $builder->where(Str::before($field, '@'), 'ilike', "%$value%");
                break;
            default:
                $builder->where($field, $value);
                break;
        }
    }

    /**
     * @param Builder $builder
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function setRelationFilter(Builder $builder, string $field, mixed $value): void
    {
        $builder->whereHas(Str::before($field, '.'), function (Builder $query) use ($value, $field) {
            $this->setFilter($query, Str::after($field, '.'), $value);
        });
    }

    /**Подставляет поиски по отдельным полям. Ключом передается название поля или отношения, значение - поисковый запрос. В названии поля отношение отделяются друг от друга точкой.
     * @param array $queries
     * @return $this
     */
    public function queries(array $queries): static
    {

        foreach ($queries as $field => $query) {
            $path = explode('.', $field);

            //todo переписать на рекурсию

            //если это поле модели
            if (count($path) === 1) {
                $this->injectQueryPart($field, $query, $this->builder);
                continue;
            }

            //если это связь в модели
            if (count($path) == 2) {
                $this->builder->whereHas(Str::camel($path[0]), function (Builder $builder) use ($query, $path) {
                    $this->injectQueryPart($path[1], $query, $builder);
                });
            }

            //если это связь другой связи
            if (count($path) == 3) {
                $this->builder->whereHas(Str::camel($path[0]), function (Builder $builder) use ($query, $path) {
                    $builder->whereHas(Str::camel($path[1]),
                        function (Builder $builder) use ($query, $path) {
                            $this->injectQueryPart($path[2], $query, $builder);
                        });
                });
            }
        }

        return $this;
    }

    private function injectQueryPart(string $field, string $query, Builder $builder): void
    {
        $fields = explode('|', $field);

        $builder->where(function (Builder $builder) use ($query, $fields) {
            foreach ($fields as $fieldName) {
                $builder->orWhere($fieldName, 'ilike', "%$query%");
            }
        });
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
        $this->builder->limit($limit);
        return $this;
    }

    /**Номер страницы
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        $this->builder->offset($offset);
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
            ->select($dto->select)
            ->limit($dto->perPage)
            ->offset($dto->page)
            ->filters($this->repository->getSupportingFilters($dto->filters))
            ->queries($dto->queries)
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

    public function exists(): bool
    {
        return $this->toQuery()->exists();
    }

    public function count(): int
    {
        return $this->toQuery()->count();
    }

    /**Возвращает объект \IPPU\Foundation\Cache\Builder с примененными правилами
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
        if (empty($this->limit || empty($this->offset))) {
            return;
        }
        $this->builder->forPage($this->offset, $this->limit);
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
