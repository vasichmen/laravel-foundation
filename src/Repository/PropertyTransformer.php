<?php

namespace Laravel\Foundation\Repository;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Foundation\Abstracts\AbstractModel;
use Laravel\Foundation\Abstracts\AbstractRepository;
use Laravel\Foundation\Abstracts\AbstractService;
use Laravel\Foundation\Repository\Traits\WorksWithRelationsTraits;

class PropertyTransformer extends AbstractService
{
    use WorksWithRelationsTraits;

    private Collection $config;

    /** @var string|TransformsProperties|AbstractRepository */
    private readonly string $repository;

    private Collection $select;
    private Collection $with;

    /**Вычисляемые поля из конфига
     * @var array
     */
    private array $calculatedFields = [];

    /**Связи, заданные напрямую, которые должны остаться после transform
     * @var array
     */
    private Collection $rootDirectRelations;

    /**Связи, нужные для расчета вычисляемых полей
     * @var array
     */
    private Collection $calculationRelations;
    private array $parameters;

    public function __construct(private readonly array $properties, string $repository)
    {
        if (!is_subclass_of($repository, AbstractRepository::class)) {
            throw new \Exception("Класс $repository должен быть репозиторием " . AbstractRepository::class);
        }
        if (!is_subclass_of($repository, TransformsProperties::class)) {
            throw new \Exception("Класс $repository должен реализовывать интерфейс " . TransformsProperties::class . " для работы с вычисляемыми свойствами");
        }

        $this->repository = $repository;
        $this->config = collect($this->repository::getPropertyConfig());
        $this->parseConfig();
    }

    private function parseConfig(): void
    {
        /** @var AbstractModel $model */
        $model = $this->repository::query()->toQuery()->getModel();
        $belongsToRelations = $model::getDefinedRelations(BelongsTo::class);

        $this->select = collect([$model->getKeyName()]);
        $this->with = collect();
        $this->calculationRelations = collect();
        $this->rootDirectRelations = collect();

        foreach ($this->properties as $property) {
            //простые поля
            if (in_array($property, [...$model->getFillable(), $model->getKeyName()])) {
                $this->select->push($property);
                continue;
            }

            //данные для вычисляемых полей
            if ($this->config->has($property)) {
                $neededWith = $this->config[$property]['with'] ?? [];
                $this->select->push(...($this->config[$property]['select'] ?? []));
                $this->with->push(...$neededWith);
                $this->addBelongsToSelect($neededWith, $belongsToRelations, $model);
                $this->calculatedFields[] = $property;
                $this->calculationRelations->push(...$neededWith);
                continue;
            }

            //связи без поля на конце
            $relationWithoutField = !!$this->findRelation($property, $model);
            $relationWithField = !!$this->findRelation(Str::beforeLast($property, '.'), $model);
            if ($relationWithField || $relationWithoutField) {
                //в зависимости от типа ключа кладем нужную связь в список
                $this->with->push($relationWithoutField ? $property : Str::beforeLast($property, '.'));

                $this->addBelongsToSelect([$property], $belongsToRelations, $model);
                $this->rootDirectRelations->push(Str::before($property, '.'));
            }
        }
        $this->select = $this->select->unique()->values();
        $this->with = $this->with->unique()->values();
        $this->rootDirectRelations = $this->rootDirectRelations->unique()->values();
        $this->calculationRelations = $this->calculationRelations->unique()->values();
    }

    /**Добавление в select полей, участвующих в belongsTo связях
     * @param array $relations
     * @param array $belongsToRelations
     * @param AbstractModel $model
     * @return void
     */
    private function addBelongsToSelect(array $relations, array $belongsToRelations, AbstractModel $model): void
    {
        foreach ($relations as $relation) {
            $root = Str::before($relation, '.');
            if (in_array($root, $belongsToRelations)) {
                $this->select->push($model->{$root}()->getForeignKeyName());
            }
        }
    }

    public function getSelect(): array
    {
        return $this->select->toArray();
    }

    public function getWith(): array
    {
        return $this->with->toArray();
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function transform(Collection|LengthAwarePaginator|AbstractModel $models): Collection|LengthAwarePaginator|AbstractModel
    {
        if ($models instanceof AbstractModel) {
            return $this->transformModel($models);
        }
        if ($models instanceof Collection) {
            return $models->transform(function (AbstractModel $item) {
                return $this->transformModel($item);
            });
        }
        if ($models instanceof LengthAwarePaginator) {
            return $models->setCollection($models->map(function (AbstractModel $item) {
                return $this->transformModel($item);
            }));
        }

        throw new \Exception();
    }

    private function transformModel(AbstractModel $model): AbstractModel
    {
        foreach ($this->calculatedFields as $field) {
            $model[$field] = $this->config[$field]['source']($model, $this->parameters ?? []);
        }

        //Очистка связей модели от тех, которые требовались только для расчета вычисляемых полей.
        //При этом связи, запрошенные напрямую, удалять нельзя
        foreach ($this->calculationRelations as $relation) {
            //если связи идут от одинаковой корневой связи, то нельзя удалять
            if ($this->rootDirectRelations->doesntContain(Str::before($relation, '.'))) {
                $model->unsetRelation($relation);
            }
        }

        return $model;
    }

}
