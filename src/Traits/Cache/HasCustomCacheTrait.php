<?php


namespace Laravel\Foundation\Traits\Cache;


use Laravel\Foundation\Abstracts\AbstractModel;

trait HasCustomCacheTrait
{
    use CacheKeysTrait;

    protected static function bootModelObservers(): void
    {
        static::created(function (AbstractModel $material) {
            self::invalidateCustomCache($material);
        });

        static::updated(function (AbstractModel $material) {
            self::invalidateCustomCache($material);
        });

        static::deleted(function (AbstractModel $material) {
            self::invalidateCustomCache($material);
        });
    }

    public static abstract function invalidateCustomCache(AbstractModel $model): void;
}
