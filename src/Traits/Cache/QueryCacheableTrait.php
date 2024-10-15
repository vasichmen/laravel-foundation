<?php


namespace Laravel\Foundation\Traits\Cache;

use Illuminate\Database\Eloquent\Model;
use Laravel\Foundation\Abstracts\AbstractModel;
use Laravel\Foundation\Cache\Builder;

trait QueryCacheableTrait
{
    protected static function invalidateCache(AbstractModel $model, $relation = null, $pivotedModels = null): void
    {
        $class = get_class($model);

        if (!$model->getCacheTagsToInvalidateOnUpdate($model, $relation, $pivotedModels)) {
            throw new \Exception('Automatic invalidation for ' . $class . ' works only if at least one tag to be invalidated is specified.');
        }

        $class::flushQueryCache(
            $model->getCacheTagsToInvalidateOnUpdate($model, $relation, $pivotedModels)
        );
    }

    /**
     * When invalidating automatically on update, you can specify
     * which tags to invalidate.
     *
     * @param  Model                                          $model
     * @param  string|null                                    $relation
     * @param  \Illuminate\Database\Eloquent\Collection|null  $pivotedModels
     * @return array
     */
    public function getCacheTagsToInvalidateOnUpdate(Model $model, $relation = null, $pivotedModels = null): array
    {
        return array_merge($this->getCacheBaseTags());
    }

    /**
     * Set the base cache tags that will be present
     * on all queries.
     *
     * @return array
     */
    public function getCacheBaseTags(): array
    {
        return [
            env('SERVICE_NAME', '') . '-' . static::class . '-list',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query)
    {
        $builder = new Builder($query);

        $builder->dontCache();

        //QueryCache
        if (method_exists($this, 'cacheForValue')) {
            $builder->cacheFor($this->cacheForValue($builder));
        }

        if ($this->cacheTags) {
            $builder->cacheTags($this->cacheTags);
        }

        if (method_exists($this, 'cacheTagsValue')) {
            $builder->cacheTags($this->cacheTagsValue($builder));
        }

        if ($this->cachePrefix) {
            $builder->cachePrefix($this->cachePrefix);
        }

        if (method_exists($this, 'cachePrefixValue')) {
            $builder->cachePrefix($this->cachePrefixValue($builder));
        }

        if ($this->cacheDriver) {
            $builder->cacheDriver($this->cacheDriver);
        }

        if (method_exists($this, 'cacheDriverValue')) {
            $builder->cacheDriver($this->cacheDriverValue($builder));
        }

        if ($this->cacheUsePlainKey) {
            $builder->withPlainKey();
        }

        if (method_exists($this, 'cacheUsePlainKeyValue')) {
            $builder->withPlainKey($this->cacheUsePlainKeyValue($builder));
        }

        return $builder->cacheBaseTags($this->getCacheBaseTags());
    }
}
