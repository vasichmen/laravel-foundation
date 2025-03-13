<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Laravel\Foundation\Abstracts\AbstractModel;

trait WorksWithRelationsTraits
{
    /**Рекурсивный поиск связи по заданному адресу. Например, systems.components_pivot
     * @param string $relationPath
     * @param AbstractModel $model
     * @return BelongsToMany
     */
    protected function findRelation(string $relationPath, AbstractModel $model): Relation
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