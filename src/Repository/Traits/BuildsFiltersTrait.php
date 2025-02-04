<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Foundation\Abstracts\AbstractModel;
use Laravel\Foundation\Cache\Builder;

trait BuildsFiltersTrait
{

    /**массив с фильтрами
     * @param array $filters
     * @return $this
     */
    public function filters(array $filters): static
    {
        foreach ($filters as $field => $value) {
            $this->setFilter($this->builder, $field, $value, $field);
        }

        return $this;
    }

    /**Установка фильтра по заданному полю в билдере с учетом модификаторов полей для операторов
     * @param Builder $builder
     * @param string $field код поля + оператор (если есть)
     * @param $value
     * @return void
     */
    private function setFilter(Builder $builder, string $field, mixed $value, string $filterCode): void
    {
        $dbField = Str::before($field, '@');
        switch (true) {
            case Str::contains($field, '.'):
                $this->setRelationFilter($builder, $field, $value, $filterCode);
                break;
            //фильтр по полям внутри json объектов. внутри могут быть такие же операторы для полей, поэтому вызываем его раньше остальных
            case Str::contains($field, '@#'):
                $path = explode(',', Str::betweenFirst($field, '@#', '@'));
                $jsonField = Str::before($field, '@#');
                $jsonField = collect([$jsonField, ...$path])->join('->');

                $fieldOperator = '';
                if (Str::substrCount($field, '@') == 2) {
                    $fieldOperator = '@' . Str::afterLast($field, '@');
                }

                $this->setFilter($builder, "$jsonField$fieldOperator", $value, $filterCode);
                break;
            //наличие ключа в json поле
            case Str::endsWith($field, '@?'):
                $builder->whereJsonContains($dbField, $value);
                break;
            case Str::endsWith($field, '@gte'):
                $builder->where($dbField, '>=', $value);
                break;
            case Str::endsWith($field, '@lte'):
                $builder->where($dbField, '<=', $value);
                break;
            case Str::endsWith($field, '@gt'):
                $builder->where($dbField, '>', $value);
                break;
            case Str::endsWith($field, '@lt'):
                $builder->where($dbField, '<', $value);
                break;
            case Str::endsWith($field, '@!'):
                switch (true) {
                    case is_array($value) || ($value instanceof Collection):
                        $builder->whereNotIn($dbField, $value);
                        break;
                    case is_null($value):
                        $builder->whereNotNull($dbField);
                        break;
                    default:
                        $builder->whereNot($dbField, $value);
                }
                break;
            case Str::endsWith($field, '@like'):
                $builder->where($dbField, 'like', "%$value%");
                break;
            case Str::endsWith($field, '@ilike'):
                $builder->where($dbField, 'ilike', "%$value%");
                break;
            case is_array($value) || ($value instanceof Collection):
                $builder->whereIn($field, $value);
                break;
            case is_null($value):
                $builder->whereNull($field);
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
    private function setRelationFilter(Builder $builder, string $field, mixed $value, string $filterCode): void
    {
        if (Str::endsWith(Str::before($field, '.'), '_pivot')) {
            //надо достать адрес текущей связи. Для этого вычитаем из кода фильтра ключ текущего фильтра и прибавляем первую связь из текущего фильтра
            $relationPath = implode('.', array_filter([trim(Str::before($filterCode, $field), '.') ?? null, Str::before($field, '.')]));
            $relation = $this->findRelation($relationPath, $this->repository->getModelInstance());

            $pivotModel = new ($relation->getPivotClass());
            $query = $pivotModel->newQuery();
            $query->whereColumn($relation->getQualifiedForeignPivotKeyName(), '=', $relation->getQualifiedParentKeyName());

            $this->setFilter($query, Str::after($field, '_pivot.'), $value, $filterCode);

            $builder->whereExists($query->toBase());
            return;
        }

        $builder->whereHas(Str::before(Str::camel($field), '.'), function (Builder $query) use ($value, $field, $filterCode) {
            $this->setFilter($query, Str::after($field, '.'), $value, $filterCode);
        });
    }

    /**Рекурсивный поиск связи по заданному адресу. Например, systems.components_pivot
     * @param string $relationPath
     * @param AbstractModel $model
     * @return BelongsToMany
     */
    private function findRelation(string $relationPath, AbstractModel $model): BelongsToMany
    {
        //если есть точка, значит это отношение, надо достать вложенное
        if (Str::contains($relationPath, '.')) {
            $relationName = Str::before($relationPath, '.');
            [$relation, $isPivot] = $this->getModelRelation($relationName, $model);
            $related = $isPivot ? new ($relation->getPivotClass()) : $relation->getRelated();

            return $this->findRelation(Str::after($relationPath, '.'), $related);
        }

        return $this->getModelRelation($relationPath, $model)[0];
    }

    /**Возвращает объект связи по заданному названию связи в модели. Учитывается суффикс _pivot
     * @param string $relationName
     * @param AbstractModel $model
     * @return array{Relation,bool}
     */
    private function getModelRelation(string $relationName, AbstractModel $model): array
    {
        $isPivot = Str::endsWith($relationName, '_pivot');
        if ($isPivot) {
            $relationName = Str::before($relationName, '_pivot');
        }
        return [$model->{Str::camel($relationName)}(), $isPivot];
    }
}
