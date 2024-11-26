<?php

namespace Laravel\Foundation\Repository;

use Closure;
use Laravel\Foundation\Abstracts\AbstractModel;
use Laravel\Foundation\Abstracts\AbstractRepository;
use Laravel\Foundation\Cache\Builder;
use Laravel\Foundation\DTO\GetListRequestDTO;
use Laravel\Foundation\Repository\Traits\BuildsCacheTrait;
use Laravel\Foundation\Repository\Traits\BuildsFiltersTrait;
use Laravel\Foundation\Repository\Traits\BuildsOrderByTrait;
use Laravel\Foundation\Repository\Traits\BuildsPaginationTrait;
use Laravel\Foundation\Repository\Traits\BuildsQueriesTrait;
use Laravel\Foundation\Repository\Traits\BuildsQueryTrait;
use Laravel\Foundation\Repository\Traits\ForwardCallsTrait;


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
 * @method RepositoryBuilder whereBetween($column, iterable $values, $boolean = 'and', $not = false)
 * @method RepositoryBuilder orWhereBetween($column, iterable $values)
 * @method RepositoryBuilder fromRaw($expression, $bindings = [])
 * @method RepositoryBuilder selectRaw($expression, array $bindings = [])
 * @method RepositoryBuilder join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method Builder newQuery()
 * @method void dd()
 * @method void ddRawSql()
 * @method void dump()
 * @method void dumpRawSql()
 */
class RepositoryBuilder
{
    use ForwardCallsTrait, BuildsCacheTrait, BuildsQueriesTrait, BuildsFiltersTrait, BuildsOrderByTrait, BuildsPaginationTrait, BuildsQueryTrait;

    private AbstractRepository $repository;
    private Builder $builder;

    private int|bool $cacheFor = true;
    private array $cacheTags = [];
    private ?string $cacheKey = null;

    private ?int $limit = null;
    private int $offset = 1;


    /**Создание нового экземпляра билдера. Устарел, вместо него используйте ::make()
     * @param string|null $repositoryNamespace
     * @deprecated вместо него используйте ::make()
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

}
