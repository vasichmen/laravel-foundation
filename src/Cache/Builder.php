<?php


namespace Laravel\Foundation\Cache;

use Closure;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use Laravel\Foundation\Traits\Cache\QueryCacheModule;
use Laravel\Foundation\Traits\EagerLoadPivotRelationsTrait;

class Builder extends BaseBuilder
{
    use QueryCacheModule, EagerLoadPivotRelationsTrait;

    /**
     * {@inheritdoc}
     */
    public function get($columns = ['*'])
    {
        return $this->shouldAvoidCache()
            ? parent::get($columns)
            : $this->getFromQueryCache('get', Arr::wrap($columns));
    }

    /**
     * {@inheritdoc}
     */
    public function useWritePdo()
    {
        // Do not cache when using the write pdo for query.
        $this->dontCache();

        // Call parent method
        parent::useWritePdo();

        return $this;
    }

    /**
     * Add a subselect expression to the query.
     *
     * @param Closure|$this|string $query
     * @param string $as
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function selectSub($query, $as)
    {
        if (!is_string($query) && get_class($query) == self::class) {
            $this->appendCacheTags($query->getCacheTags() ?? []);
        }

        return parent::selectSub($query, $as);
    }

    /**
     * Override.
     * Eagerly load the relationship on a set of models.
     *
     * @param  array  $models
     * @param  string  $name
     * @param  Closure  $constraints
     * @return array
     */
    protected function eagerLoadRelation(array $models, $name, Closure $constraints)
    {
        $this->watchForPivotAccessors($name);

        if ($this->isPivotAccessor($name)) {
            $this->eagerLoadPivotRelations($models, $name);
            return $models;
        }

        return parent::eagerLoadRelation($models, $name, $constraints);
    }
}
