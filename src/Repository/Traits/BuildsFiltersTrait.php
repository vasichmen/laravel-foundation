<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Foundation\Abstracts\AbstractModel;
use Laravel\Foundation\Cache\Builder;

trait BuildsFiltersTrait
{
    use WorksWithRelationsTraits;

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
     * @param mixed $value
     * @param string $filterCode
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
            //наличие ключа в json поле. Если передать массив, то должны быть все ключи из массива
            case Str::endsWith($field, '@?'):
                $builder->whereJsonContains($dbField, $value);
                break;
            //отсутствие ключа в json поле. Если передать массив, то должны отсутствовать все ключи из массива
            case Str::endsWith($field, '@?!'):
                $builder->whereJsonDoesntContain($dbField, $value);
                break;
            //наличие ключа в json поле. Если передать массив, то должен быть хотя бы один ключ из массива
            case Str::endsWith($field, '@?|'):
                $value = "['" . collect($value)->join("','") . "']";
                $builder->whereRaw(Str::before($field, '@?') . "::jsonb ??| array$value");
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
                /** @var AbstractModel $model */
                $model = $builder->getModel();
                $relations = $model::getDefinedRelations([BelongsToMany::class, HasMany::class, HasOne::class]);
                $casts = $model->getCasts();
                switch (true) {
                    //json поля с массивами
                    case in_array($casts[$field] ?? null, ['array', 'collection']):
                        $builder->whereRaw("($field::jsonb in ('[]'::jsonb,'{}'::jsonb) or $field is null)");
                        break;
                    //множественные связи
                    case in_array(Str::camel($field), $relations):
                        $builder->whereHas(Str::camel($field), operator: '=', count: 0);
                        break;
                    //простые поля
                    default:
                        $builder->whereNull($field);
                        break;
                }
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
        //если название связи начинается с !, то добавляем отрицание
        $not = Str::startsWith($field, '!');

        //pivot-связь
        if (Str::endsWith(Str::before($field, '.'), '_pivot')) {
            //надо достать адрес текущей связи. Для этого вычитаем из кода фильтра ключ текущего фильтра и прибавляем первую связь из текущего фильтра
            $relationPath = implode('.', array_filter([trim(Str::before($filterCode, $field), '.') ?? null, Str::before($field, '.')]));

            //в пути надо убрать все !, они в данном случае не важны
            $relationPath = Str::replace('!', '', $relationPath);

            $relation = $this->findRelation($relationPath, $this->repository->getModelInstance());

            $pivotModel = new ($relation->getPivotClass());
            $query = $pivotModel->newQuery();
            $query->whereColumn($relation->getQualifiedForeignPivotKeyName(), '=', $relation->getQualifiedParentKeyName());

            $this->setFilter($query, Str::after($field, '_pivot.'), $value, $filterCode);

            $builder->whereExists($query->toBase(), not: $not);
            return;
        }

        //обычная связь
        $relationName = Str::replace('!', '', Str::before(Str::camel($field), '.'));
        $closure = function (Builder $query) use ($value, $field, $filterCode) {
            $this->setFilter($query, Str::after($field, '.'), $value, $filterCode);
        };

        if ($not) {
            $builder->whereDoesntHave($relationName, $closure);
        } else {
            $builder->whereHas($relationName, $closure);
        }
    }

}
