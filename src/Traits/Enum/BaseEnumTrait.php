<?php


namespace Laravel\Foundation\Traits\Enum;

use Illuminate\Support\Collection;
use Laravel\Foundation\Exceptions\EnumTransNotFoundException;

/**
 * @method cases():array
 */
trait BaseEnumTrait
{
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

    /**список всех значений этого enum
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @throws EnumTransNotFoundException
     */
    private static function getTransId(string $code): string
    {
        $key = 'enums.' . static::class . '.' . $code;
        if (trans()->has($key)) {
            return $key;
        }
        throw new EnumTransNotFoundException('Для класса ' . static::class . ' не найден перевод ' . $key);
    }

    /**Получить название enum из lang.enums.[class_name]
     * @param array $args
     * @return string
     * @throws EnumTransNotFoundException
     */
    public function trans(array $args = []): string
    {
        return trans(self::getTransId($this->value), $args);
    }

    /**Получение объекта перечисления по его переводу
     * @param string $translation
     * @return \UnitEnum
     * @throws \Exception
     */
    public static function fromTrans(string $translation): self
    {
        foreach (trans('enums.' . static::class) as $key => $trans) {
            if ($trans === $translation) {
                return self::from($key);
            }
        }

        throw new \Exception('Не найден ключ по переводу ' . $translation);
    }
}