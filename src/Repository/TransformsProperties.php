<?php

namespace Laravel\Foundation\Repository;

interface TransformsProperties
{
    /**Возвращает конфиг вычисляемого поля и список зависимостей
     * @return array{array{with:string[],select:string[],source:callable}}
     */
    public static function getPropertyConfig(): array;
}
