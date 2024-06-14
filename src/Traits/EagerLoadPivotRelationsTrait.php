<?php

namespace Laravel\Foundation\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait EagerLoadPivotRelationsTrait
{
    protected static $knownPivotAccessors = [
        'pivot',
    ];


    /**
     * Watch for pivot accessors to register it as known pivot accessors.
     *
     * @param  string $name
     * @return void
     */
    private function watchForPivotAccessors($name)
    {
        $model = $this->getModel();

        if (!method_exists($model->newInstance(), $name)) {
            return;
        }

        $relation = $model->newInstance()->$name();

        if ($relation instanceof BelongsToMany) {
            static::$knownPivotAccessors[] = $relation->getPivotAccessor();
        }
    }

    /**
     * If relation name is a pivot accessor.
     *
     * @param  string  $name
     * @return boolean
     */
    private function isPivotAccessor($name)
    {
        return in_array($name, static::$knownPivotAccessors);
    }

    /**
     * Eager load pivot relations.
     *
     * @param  array $models
     * @param  string $pivotAccessor
     * @return void
     */
    private function eagerLoadPivotRelations($models, $pivotAccessor)
    {
        $pivots = Arr::pluck($models, $pivotAccessor);
        $pivots = head($pivots)->newCollection($pivots);
        $pivots->load($this->getPivotEagerLoadRelations($pivotAccessor));
    }

    /**
     * Get the pivot relations to be eager loaded.
     *
     * @param string $pivotAccessor
     * @return array
     */
    private function getPivotEagerLoadRelations($pivotAccessor)
    {
        $relations = array_filter($this->eagerLoad, function ($relation) use ($pivotAccessor) {
            return $relation !== $pivotAccessor && Str::contains($relation, $pivotAccessor);
        }, ARRAY_FILTER_USE_KEY);

        return array_combine(
            array_map(function ($relation) use ($pivotAccessor) {
                return substr($relation, strlen("{$pivotAccessor}."));
            }, array_keys($relations)),
            array_values($relations)
        );
    }
}
