<?php


namespace Laravel\Foundation\Traits;

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
