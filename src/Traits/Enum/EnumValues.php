<?php


namespace Laravel\Foundation\Traits\Enum;

/** @deprecated  */
trait EnumValues
{
    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
