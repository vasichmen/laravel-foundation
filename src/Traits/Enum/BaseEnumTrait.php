<?php


namespace Laravel\Foundation\Traits\Enum;

use Illuminate\Support\Collection;
use Laravel\Foundation\Exceptions\EnumTransNotFoundException;

/**
 * @method cases():array
 */
trait BaseEnumTrait
{
    static string $langFileName = 'enums';

    /**Получить название enum из lang.enums.[class_name]
     * @param array $args
     * @return string
     * @throws EnumTransNotFoundException
     */
    public function trans(array $args = []): string
    {
        return trans(self::getTransId($this->value), $args);
    }

    /**список всех значений этого enum
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**Список всех значений с переводами
     * @param array<array> $args массив параметров для подстановки
     * @return Collection
     * @throws EnumTransNotFoundException
     */
    public static function list(array $args = []): Collection
    {
        return collect(self::values())
            ->map(fn($value) => [
                'id' => $value,
                'name' => trans(self::getTransId($value), $args[$value] ?? []),
            ]);
    }

    /**
     * @throws EnumTransNotFoundException
     */
    private static function getTransId(string $code): string
    {
        $key = self::$langFileName . '.' . static::class . '.' . $code;
        if (trans()->has($key)) {
            return $key;
        }
        throw new EnumTransNotFoundException('Для класса ' . static::class . ' не найден перевод ' . $key);
    }
}