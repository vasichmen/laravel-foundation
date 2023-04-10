<?php


namespace Laravel\Foundation\Traits;

trait EnumTranslatable
{
    public function trans(array $args = []): string
    {
        return trans('enums.' . static::class . '.' . $this->value, $args);
    }
}
