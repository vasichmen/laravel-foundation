<?php


namespace Laravel\Foundation\Traits\Enum;

use Illuminate\Support\Collection;
use Laravel\Foundation\Exceptions\EnumTransNotFoundException;
use UnitEnum;

/**
 * @method cases():array
 */
trait BaseEnumTrait
{
    /**Список всех значений с переводами
     * @param array<array> $args массив параметров для подстановки
     * @return Collection
     */
    public static function list(array $args = []): Collection
    {
        return collect(self::cases())
            ->map(fn(self $enum) => $enum->render());
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
        $key = self::getNamespace() . 'enums.' . static::class . '.' . $code;
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

    public function render(): array
    {
        return [
            'id' => $this->value,
            'name' => $this->trans(),
        ];
    }

    /**Получение объекта перечисления по его переводу
     * @param string $translation
     * @return UnitEnum
     * @throws \Exception
     */
    public static function fromTrans(string $translation): self
    {
        if ($result = self::tryFromTrans($translation)) {
            return $result;
        }

        throw new \Exception('Не найден ключ по переводу "' . $translation . '"');
    }

    /**Получение объекта перечисления по его переводу
     * @param string $translation
     * @return ?UnitEnum
     */
    public static function tryFromTrans(string $translation): ?self
    {
        foreach (trans(self::getNamespace() . 'enums.' . static::class) as $key => $trans) {
            if ($trans === $translation) {
                return self::from($key);
            }
        }

        return null;
    }

    /**Пространство имен для локализации
     * @return string
     */
    protected static function getNamespace(): string
    {
        return '';
    }

}