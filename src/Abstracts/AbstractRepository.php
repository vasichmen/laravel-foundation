<?php namespace Laravel\Foundation\Abstracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Foundation\Traits\Cache\CacheKeysTrait;

abstract class AbstractRepository
{
    use CacheKeysTrait;

    protected AbstractModel $model;

    public function __construct(AbstractModel $model)
    {
        $this->model = $model;
    }

    /**Собирает запрос в БД по заданным условиям и возвращает перую найденную запись или null
     * @param  array        $filters    фильтры для запроса [поле => значение]
     * @param  array        $with       аналогичен Builder->with()
     * @param  array        $select     аналогичен Builder->select()
     * @param  array        $orderBy    аналогичен Builder->orderBy()
     * @param  int|bool     $cacheFor   время кэширования или false, если не требуется кэширование. -1 для кэширования
     *                                  без ограничения по времени. По умолчанию время берется из config('cache.ttl')
     * @param  int|null     $limit      число записей на странице
     * @param  int|null     $offset     номер страницы
     * @param  bool         $paginate   Вернуть обьект пагинации или нет?
     * @param  array|null   $cacheTags  список кастомных тегов для кэша
     * @param  string|null  $cacheKey   кастомный ключ кэша
     * @return Collection| LengthAwarePaginator
     */
    public final function getList(
        array $filters = [],
        array $with = [],
        array $select = ['*'],
        array $orderBy = [],
        int|bool $cacheFor = true,
        ?int $limit = null,
        ?int $offset = null,
        bool $paginate = false,
        ?array $cacheTags = null,
        ?string $cacheKey = null,
    ): Collection|LengthAwarePaginator {
        if ($paginate) {
            return $this->buildQuery($filters, $with, $select, $orderBy, $cacheFor, cacheTags: $cacheTags, cacheKey: $cacheKey)
                ->paginate(
                    perPage: $limit,
                    page: $offset,
                );
        }


        return $this->buildQuery($filters, $with, $select, $orderBy, $cacheFor, $limit, $offset, $cacheTags, $cacheKey)
            ->get();
    }

    /**Собирает запрос в БД по заданным условиям
     * @param  array        $filters    фильтры для запроса [поле => значение]
     * @param  array        $with       аналогичен Builder->with()
     * @param  array        $select     аналогичен Builder->select()
     * @param  array        $orderBy    аналогичен Builder->orderBy()
     * @param  int|bool     $cacheFor   время кэширования или false, если не требуется кэширование. -1 для кэширования
     *                                  без ограничения по времени. По умолчанию время берется из config('cache.ttl')
     * @param  int|null     $limit      число записей на странице
     * @param  int|null     $offset     номер страницы
     * @param  array|null   $cacheTags  список кастомных тегов для кэша
     * @param  string|null  $cacheKey   кастомный ключ кэша
     * @return Builder
     */
    public final function buildQuery(array $filters = [], array $with = [], array $select = ['*'], array $orderBy = [], int|bool $cacheFor = true, ?int $limit = null, ?int $offset = null, ?array $cacheTags = null, ?string $cacheKey = null): Builder
    {
        $query = $this->model;

        if ($cacheFor !== false) {
            $cacheFor = $cacheFor === true ? config('cache.ttl') : $cacheFor;
            $query = $query->cacheFor($cacheFor);
        }

        if (!is_null($cacheTags)) {
            $query = $query->cacheTags($cacheTags);
        }

        if (!is_null($cacheKey)) {
            $query = $query->cacheKey($cacheKey);
        }

        $query = $query->with($with)->select($select);

        foreach ($filters as $field => $value) {
            if (is_array($value) || ($value instanceof Collection)) {
                $query = $query->whereIn($field, $value);
            }
            else {
                if (is_null($value)) {
                    $query = $query->whereNull($field);
                }
                else {
                    $query = $query->where($field, $value);
                }
            }
        }

        foreach ($orderBy as $column => $direction) {
            $query = $query->orderBy($column, $direction);
        }

        if (!empty($limit) && !empty($offset)) {
            $query = $query->when($offset, function ($query, $offset) use ($limit) {
                $query->forPage($offset, $limit);
            });
        }

        return $query;
    }

    public function delete(string|AbstractModel $model): ?bool
    {
        $model = $this->getModel($model);
        return $model?->forceDelete();
    }

    /**Возвращает модель по id. Если передать null или объект, то вернет обратно. Метод учитывает кэширование
     * @param  string|AbstractModel|null  $model
     * @return AbstractModel|null
     */
    public final function getModel(string|AbstractModel|null $model): ?AbstractModel
    {
        if (empty($model)) {
            return null;
        }
        if (!($model instanceof AbstractModel)) {
            $model = $this->find(['id' => $model]);
        }
        return $model;
    }

    /**Собирает запрос в БД по заданным условиям и возвращает перую найденную запись или null
     * @param  array        $filters    фильтры для запроса [поле => значение]
     * @param  array        $with       аналогичен Builder->with()
     * @param  array        $select     аналогичен Builder->select()
     * @param  array        $orderBy    аналогичен Builder->orderBy()
     * @param  int|bool     $cacheFor   время кэширования или false, если не требуется кэширование. -1 для кэширования
     *                                  без ограничения по времени. По умолчанию время берется из config('cache.ttl')
     * @param  array|null   $cacheTags  список кастомных тегов для кэша
     * @param  string|null  $cacheKey   кастомный ключ кэша
     * @return AbstractModel|null
     */
    public final function find(array $filters = [], array $with = [], array $select = ['*'], array $orderBy = [], int|bool $cacheFor = true, ?array $cacheTags = null, ?string $cacheKey = null): ?AbstractModel
    {
        return $this->buildQuery($filters, $with, $select, $orderBy, $cacheFor, cacheTags: $cacheTags, cacheKey: $cacheKey)
            ->take(1)
            ->get()
            ->first();
    }

    public function create(array|AbstractDto $params): AbstractModel
    {
        if ($params instanceof AbstractDto) {
            $params = $params->toArray();
        }
        return $this->model->newQuery()->create($params);
    }

    public function insert(array $params): bool
    {
        return $this->model->newQuery()->insert($params);
    }

    /**Обновление модели
     * @param string|AbstractModel $model Если передана строка, то для нее будет вызван метод getModel
     * @param array|AbstractDto $params Если передан AbstractDto, то вызовется AbstractDto::toArray()
     * @return bool|AbstractModel false, если не удалось обновить или найти модель
     * @throws \ReflectionException
     */
    public function update(string|AbstractModel $model, array|AbstractDto $params): bool|AbstractModel
    {
        $model = $this->getModel($model);
        if ($params instanceof AbstractDto) {
            $params = $params->toArray();
        }

        if (!empty($model)) {
            $model->fill($params);
            if ($model->save()) {
                return $model;
            }
            return false;
        }
        return false;
    }
}
