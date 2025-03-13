<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Laravel\Foundation\Cache\Builder;

trait BuildsOrderByTrait
{
    use WorksWithRelationsTraits;

    /**настройка сортировки
     * @param array $orderBy
     * @return $this
     */
    public function orderBy(array $orderBy): static
    {
        foreach ($orderBy as $column => $direction) {
            if (Str::contains($column, '.')) {
                $this->setRelationOrderBy($this->builder, $column, $direction);
            } else {
                $this->builder->orderBy($column, $direction);
            }
        }
        return $this;
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

        $lastRelationTableName = $this->findRelation($sortByRelation, $builder->getModel())->getRelated()->getTable();

        $firstRelation = $builder->getRelation(Str::before($sortByRelation, '.'));
        self::checkRelation($firstRelation, [Str::before($sortByRelation, '.')]);

        $subQuery = $firstRelation->getQuery();
        $firstModel = $firstRelation->getRelated();
        $innerRelationPath = Str::beforeLast(Str::after($sortByRelation, '.'), '.');
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

        $subQuery->select($lastRelationTableName . '.' . Str::afterLast($column, '.'));

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
