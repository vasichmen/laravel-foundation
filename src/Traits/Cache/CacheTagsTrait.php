<?php


namespace Laravel\Foundation\Traits\Cache;

trait CacheTagsTrait
{
    protected static function generateCacheTag(...$args): string
    {
        $tag = 'cache|tag';
        foreach ($args as $arg) {
            $tag .= '|';
            switch (gettype($arg)) {
                case 'string':
                case 'integer':
                case 'boolean':
                case 'double':
                    $tag .= (string) $arg;
                    break;
                case 'array':
                case 'object':
                    $tag .= md5(serialize($arg));
                    break;
                case 'NULL':
                    $tag .= 'null';
                    break;
            }
        }
        return $tag;
    }
}
