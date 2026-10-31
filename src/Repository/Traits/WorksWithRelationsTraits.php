<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Laravel\Foundation\Abstracts\AbstractModel;

trait WorksWithRelationsTraits
{
    /**Рекурсивный поиск связи по заданному адресу. Например, systems.components_pivot
     * @param string $relationPath
     * @param AbstractModel $model
     * @return ?Relation
     */
    protected function findRelation(string $relationPath, AbstractModel $model): ?Relation
    {
        //если в связи есть точка, то это может быть вложенная связь, либо связь внутри pivot-связи
        //если после связи идет ".pivot", значит это pivot-связь и надо заменить в неё . на _ между названием связи и "pivot"
        //пример: currentVersion.components.pivot.componentVersion => currentVersion.components_pivot.componentVersion
        $relationPath = Str::replace(
            Str::before($relationPath, '.') . '.pivot',
            Str::before($relationPath, '.') . '_pivot',
            $relationPath
        );

        //если есть точка, значит это отношение, надо достать вложенное
        if (Str::contains($relationPath, '.')) {
            $relationName = Str::before($relationPath, '.');
            $modelRelation = $this->getModelRelation($relationName, $model);
            if (empty($modelRelation)) {
                return null;
            }
            [$relation, $isPivot] = $modelRelation;
            $related = $isPivot ? new ($relation->getPivotClass()) : $relation->getRelated();

            return $this->findRelation(Str::after($relationPath, '.'), $related);
        }

        return $this->getModelRelation($relationPath, $model)[0] ?? null;
    }


    /**Возвращает объект связи по заданному названию связи в модели. Учитывается суффикс _pivot
     * @param string $relationName
     * @param AbstractModel $model
     * @return ?array{Relation,bool}
     */
    private function getModelRelation(string $relationName, AbstractModel $model): ?array
    {
        $isPivot = Str::endsWith($relationName, '_pivot');
        if ($isPivot) {
            $relationName = Str::before($relationName, '_pivot');
        }
        if (method_exists($model, Str::camel($relationName))) {
            return [$model->{Str::camel($relationName)}(), $isPivot];
        }
        return null;
    }
}
