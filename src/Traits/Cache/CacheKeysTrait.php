<?php


namespace Laravel\Foundation\Traits\Cache;

trait CacheKeysTrait
{
    protected static function getCacheKey(...$args): string
    {
        return self::generateCacheKey('cache|key', $args);
    }

    protected static function generateCacheKey($prefix, ...$args): string
    {
        if (!str_ends_with($prefix, '|')) {
            $prefix .= '|';
        }
        return $prefix . md5(serialize($args));
    }

    protected static function getCacheTag($prefix, ...$args): string
    {
        return self::generateCacheKey("cache|key|$prefix", $args);
    }
}
