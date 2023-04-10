<?php


namespace Laravel\Foundation\Abstracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Laravel\Foundation\Traits\Cache\HasCustomCacheTrait;
use Laravel\Foundation\Traits\Cache\QueryCacheableTrait;

/**
 * @method static bool flushQueryCache(string[] $tags = [])
 * @method static bool flushQueryCacheWithTag(string $tag)
 * @method static Builder|static cacheFor(\DateTime|int $ttl)
 * @method static Builder|static cacheForever()
 * @method static Builder|static dontCache(bool $avoidCache = true)
 * @method static Builder|static doNotCache(bool $avoidCache = true)
 * @method static Builder|static cachePrefix(string $prefix)
 * @method static Builder|static cacheTags(array $cacheTags = [])
 * @method static Builder|static appendCacheTags(array $cacheTags = [])
 * @method static Builder|static cacheDriver(string $cacheDriver)
 * @method static Builder|static cacheBaseTags(array $tags = [])
 */
abstract class AbstractModel extends Model
{
    use  QueryCacheableTrait, HasCustomCacheTrait;

    protected static bool $fillsCode = false;

    public static function boot()
    {
        static::bootUuid();
        static::bootCode();
        static::bootObservers();

        if (method_exists(static::class, 'bootModelObservers')) {
            static::bootModelObservers();
        }

        parent::boot();
    }

    /**
     * Register model event's handlers
     * Events uses for generate uuid
     */
    protected static function bootUuid()
    {
        static::creating(function ($model) {
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::orderedUuid();
            }
        });

        static::saving(function ($model) {
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::orderedUuid();
            }
        });
    }

    protected static function bootCode()
    {
        static::creating(function ($model) {
            if (!$model->code && static::$fillsCode) {
                $model->code = self::generateCode($model->name);
            }
        });

        static::saving(function ($model) {
            if (!$model->code && static::$fillsCode) {
                $model->code = self::generateCode($model->name);
            }
        });
    }

    public static function generateCode(string $name): string
    {
        $initialCode = $code = Str::slug($name);
        $number = 1;

        while (self::where('code', $code)->first()) {
            $code = $initialCode . '-' . $number++;
        }

        return $code;
    }

    /**
     * При переопределении обязателен вызов родительского метода
     */
    protected static function bootObservers()
    {
        static::created(function (AbstractModel $model) {
            static::invalidateCache($model);
        });

        static::updated(function (AbstractModel $model) {
            static::invalidateCache($model);
        });

        static::deleted(function (AbstractModel $model) {
            static::invalidateCache($model);
        });
    }

    /**
     * Disable autoincrement for primary key for uuid support
     * Autoincrement for field "number" performed by database driver
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Set primary key type for uuid support
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'string';
    }

    public function invalidateAllCache()
    {
        //сброс общего кэша
        static::invalidateCache($this);

        //если есть метод сброса кастомного кэша, то вызываем его
        if (method_exists($this, 'invalidateCustomCache')) {
            static::invalidateCustomCache($this);
        }
    }

    public static function invalidateCustomCache(AbstractModel $model): void
    {

    }
}
