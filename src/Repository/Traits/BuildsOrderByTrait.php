<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Laravel\Foundation\Cache\Builder;
use Laravel\Foundation\Traits\Enum\BaseEnumTrait;

trait BuildsOrderByTrait
{
    use WorksWithRelationsTraits;

    /**настройка сортировки
     * @param array $orderBy
     * @return $this
     */
    public function orderBy(array $orderBy): static
    {
        $model = $this->builder->getModel();
        $fillable = $model->getFillable();
        $timestamps = $model->usesTimestamps() ? [$model::CREATED_AT, $model::UPDATED_AT] : [];
        $casts = $model->getCasts();

        foreach ($orderBy as $column => $direction) {
            $column = Str::snake($column);
            if (Str::contains($column, '.')) {
                $this->setRelationOrderBy($this->builder, $column, $direction);
            } else {
                if (in_array($column, [...$fillable, ...$timestamps])) {
                    $this->builder->orderByRaw(self::prepareField($casts, $column) . " $direction");
                }
            }
        }
        return $this;
    }

    /**Подготовка выражения для select нужного столбца
     * @param array $casts
     * @param string $field
     * @param string|null $prefix
     * @return string
     */
    private static function prepareField(array $casts, string $field, ?string $prefix = null): string
    {
        if (!array_key_exists($field, $casts)) {
            return $field;
        }

        $type = $casts[$field];
        $prefixed = implode('.', array_filter([$prefix, $field]));
        switch (true) {
            case class_exists($type) && is_subclass_of($type, \UnitEnum::class):
                /** @var BaseEnumTrait $type */
                return 'case '
                    . $type::list()
                        ->map(static fn(array $item) => "when $prefixed = '{$item['id']}' then '{$item['name']}'")
                        ->join(' ')
                    . ' end';
            default:
                return $prefixed;
        }
    }

    /**Установка сортировки по полю из связи
     * @param Builder $builder
     * @param string $column
     * @param string $direction
     * @return void
     * @throws \Exception
     */
    private function setRelationOrderBy(Builder $builder, string $column, string $direction): void
    {
        $sortByRelation = Str::camel(Str::beforeLast($column, '.'));

        $lastRelationModel = $this->findRelation($sortByRelation, $builder->getModel())->getRelated();

        $firstRelation = $builder->getRelation(Str::before($sortByRelation, '.'));
        self::checkRelation($firstRelation, [Str::before($sortByRelation, '.')]);

        $subQuery = $firstRelation->getQuery();
        $firstModel = $firstRelation->getRelated();
        $innerRelationPath = Str::after($sortByRelation, '.');
        $relationPath = [];
        if (Str::contains($sortByRelation, '.')) {
            foreach (explode('.', $innerRelationPath) as $relationName) {
                $relationPath[] = $relationName;
                $relation = $this->findRelation(implode('.', $relationPath), $firstModel);

                self::checkRelation($relation, $relationPath);

                $relatedTable = $relation->getRelated()->getTable();
                [$firstKey, $secondKey] = $this->getRelationKeys($relation);
                $subQuery->leftJoin($relatedTable, $firstKey, '=', $secondKey);
            }
        }

        $preparedColumnExpression = self::prepareField($lastRelationModel->getCasts(), Str::afterLast($column, '.'), $lastRelationModel->getTable());
        $subQuery->selectRaw($preparedColumnExpression);

        [$firstKey, $secondKey] = $this->getRelationKeys($firstRelation);
        $subQuery->whereColumn($firstKey, $secondKey);

        $builder->orderBy($subQuery, $direction);
    }

    /**Проверка, что отношение допустимого типа. Принимаются только типы с одиночными привязками
     * @param Relation $relation
     * @param array $relationPath
     * @return void
     * @throws \Exception
     */
    private function checkRelation(Relation $relation, array $relationPath): void
    {
        if ($relation instanceof BelongsTo || $relation instanceof HasOne) {
            return;
        }
        throw new \Exception("Связь [" . implode('.', $relationPath) . "] должна быть BelongsTo или HasOne");
    }

    /**Возвращает названия ключей с таблицами для условий объединения
     * @param BelongsTo|HasOne $relation
     * @return array{0:string,1:string}
     */
    private function getRelationKeys(BelongsTo|HasOne $relation): array
    {
        return match (true) {
            $relation instanceof HasOne => [$relation->getQualifiedForeignKeyName(), $relation->getQualifiedParentKeyName()],
            $relation instanceof BelongsTo => [$relation->getQualifiedForeignKeyName(), $relation->getQualifiedOwnerKeyName()],
            default => throw new \Exception('Этот тип связи не реализован'),
        };
    }
}
