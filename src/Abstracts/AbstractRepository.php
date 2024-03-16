<?php

namespace Laravel\Foundation\Abstracts;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Foundation\Repository\RepositoryBuilder;
use Laravel\Foundation\Traits\Cache\CacheKeysTrait;

abstract class AbstractRepository
{
    use CacheKeysTrait;

    protected AbstractModel $model;
    protected static string $repositoryBuilderClass = RepositoryBuilder::class;

    public function __construct(AbstractModel $model, string $repositoryBuilderClass = RepositoryBuilder::class)
    {
        $this->model = $model;
        static::$repositoryBuilderClass = $repositoryBuilderClass;
    }

    /**Возвращает модель по id. Если передать null или модель, то вернет обратно. Метод учитывает кэширование
     * @param string|AbstractModel|null $model
     * @param array $with массив связей, которые будут загружены
     * @return AbstractModel|null
     */
    public static final function getModel(string|AbstractModel|null $model, array $with = []): ?AbstractModel
    {
        if (empty($model)) {
            return null;
        }
        if (!($model instanceof AbstractModel)) {
            $model = static::query()->filters(['id' => $model])->with($with)->find();
        }
        return $model;
    }

    public static function create(array|AbstractDto $params): AbstractModel
    {
        if ($params instanceof AbstractDto) {
            $params = $params->toArray();
        }
        return static::newQuery()->create($params);
    }

    public static function insert(array $params): bool
    {
        return static::newQuery()->insert($params);
    }

    /**Обновление модели
     * @param string|AbstractModel $model Если передана строка, то для нее будет вызван метод getModel
     * @param array|AbstractDto $params Если передан AbstractDto, то вызовется AbstractDto::toArray()
     * @return bool|AbstractModel false, если не удалось обновить или найти модель
     * @throws \ReflectionException
     */
    public static function update(string|AbstractModel $model, array|AbstractDto $params): bool|AbstractModel
    {
        $model = static::getModel($model);
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

    /**Обновляет существующую модель, найденную по параметрам $attributes, или создает новую
     * @param array $attributes
     * @param array $values
     * @return AbstractModel
     */
    public static function updateOrCreate(array $attributes, array $values): AbstractModel
    {
        return static::newQuery()->updateOrCreate($attributes, $values);
    }

    /**Удаление модели
     * @param string|AbstractModel $model
     * @return bool|null
     */
    public static function delete(string|AbstractModel $model): ?bool
    {
        $model = static::getModel($model);
        return $model?->forceDelete();
    }

    /**Возвращает объект репозитория
     * @return AbstractRepository
     */
    protected static function getInstance(): AbstractRepository
    {
        return app(static::class);
    }

    /**Возвращает новый объект \Illuminate\Database\Eloquent\Builder
     * @return Builder
     */
    public static function newQuery(): Builder
    {
        return self::getInstance()->model->newQuery();
    }

    /**Возвращает построитель запросов репозиториев
     * @return RepositoryBuilder
     */
    public static function query(): RepositoryBuilder
    {
        $class = static::$repositoryBuilderClass;
        return new $class(static::class);
    }
}
