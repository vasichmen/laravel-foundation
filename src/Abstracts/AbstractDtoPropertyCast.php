<?php

namespace Laravel\Foundation\Abstracts;


use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Класс для приведения типов json полей в моделях к DTO объектам.
 */
abstract class AbstractDtoPropertyCast implements CastsAttributes
{
    protected string $dtoClassName;

    public function get($model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }
        $className = $this->dtoClassName;
        return new $className(json_decode($value, true));
    }

    public function set($model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof AbstractDto) {
            return json_encode($value->toArray());
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_null($value)) {
            return null;
        }
        throw new \Exception('Некорректный тип значения');
    }
}
