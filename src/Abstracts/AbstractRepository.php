<?php

namespace Laravel\Foundation\Abstracts;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Foundation\Cache\Builder;
use Laravel\Foundation\Repository\RepositoryBuilder;
use Laravel\Foundation\Traits\Cache\CacheKeysTrait;

abstract class AbstractRepository
{
    use CacheKeysTrait;

    protected ?AbstractModel $model;
    protected static string $repositoryBuilderClass = RepositoryBuilder::class;

    public function __construct(?AbstractModel $model, string $repositoryBuilderClass = RepositoryBuilder::class)
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

    public function setModel(AbstractModel $model): AbstractRepository
    {
        $this->model = $model;
        return $this;
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

    /**Получает существующую модель, найденную по параметрам $attributes, или создает новую
     * @param array $attributes
     * @param array $values
     * @return AbstractModel
     */
    public static function firstOrCreate(array $attributes = [], array $values = []): AbstractModel
    {
        return static::newQuery()->firstOrCreate($attributes, $values);
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
        /** @var RepositoryBuilder $class */
        $class = static::$repositoryBuilderClass;
        return $class::make(self::getInstance(), null);
    }


    /**Возвращает инстанс модели
     * @return AbstractModel
     */
    public function getModelInstance(): AbstractModel
    {
        return $this->model;
    }

    /**Оставляет в массиве фильтров только пересекающиеся поля с fillable модели
     * @param array|Collection $filters массив фильтров из запроса
     * @param array|Collection $additionalFilters дополнительные ключи, которые надо оставить в фильтрах
     * @return array
     */
    public function getSupportingFilters(array|Collection $filters, array|Collection $additionalFilters = []): array
    {
        $filters = collect($filters);
        $additionalFilters = collect($additionalFilters);
        $modelFilters = $this->getModelFilters($this->model, $filters->keys());

        //объединяем с фильтрами-исключениями
        $filterableFields = $additionalFilters->merge($modelFilters);

        //оставляем только те фильтры, которые поддерживаются этой моделью
        return $filters
            ->filter(static fn($value, $key) => $filterableFields->contains($key))
            ->toArray();
    }

    /**Возвращает список ключей фильтров, которые доступны в указанной модели
     * @param AbstractModel $model
     * @param Collection|array $filterCodes
     * @return Collection
     */
    private function getModelFilters(AbstractModel $model, Collection|array $filterCodes): Collection
    {
        $fillable = $model->getFillable();
        $relations = $model::getDefinedRelations();
        $timestamps = $model->usesTimestamps() ? [$model::CREATED_AT, $model::UPDATED_AT] : [];
        $result = collect();
        foreach (collect($filterCodes) as $filterCode) {
            $fieldName = Str::before($filterCode, '@');

            if (
                in_array($fieldName, $fillable) ||  //это поле есть в списке полей модели
                in_array($fieldName, $timestamps) ||  //это поле одно из меток времени
                $model->getKeyName() === $fieldName //поле является первичным ключом
            ) {
                $result[] = $filterCode;
                continue;
            }

            //если это составное поле, то проверяем, является это отношением или нет
            if (Str::contains($fieldName, '.')) {
                $relationFilterName = Str::before($fieldName, '.');
                $relationName = Str::camel($relationFilterName);

                //если такое отношение существует
                if (in_array($relationName, $relations)) {
                    /** @var Relation $relation */
                    $relation = $model->{$relationName}();
                    /** @var AbstractModel $relatedModel */
                    $relatedModel = $relation->getRelated();
                    $relatedFieldName = Str::after($filterCode, '.');
                    $availableRelatedFilters = $this->getModelFilters($relatedModel, [$relatedFieldName]);
                    if (count($availableRelatedFilters) > 0) {
                        $result[] = $relationFilterName . '.' . $relatedFieldName;
                    }
                    continue;
                }
            }
        }
        return $result;
    }
}
