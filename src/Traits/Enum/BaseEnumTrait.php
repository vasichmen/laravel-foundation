<?php

namespace Laravel\Foundation\Traits\Enum;

use Illuminate\Support\Collection;
use Laravel\Foundation\Exceptions\EnumTransNotFoundException;
use UnitEnum;

/**
 * @method array cases
 * @method self|UnitEnum from(string|int $key)
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
     * @param string|int|null $code Если null, то возвращает id всего блока для этого Enum
     * @param bool $throwIfNotExists
     * @return string|null
     */
    private static function getTransNameId(null|int|string $code, bool $throwIfNotExists = true): ?string
    {
        //для обратной совместимости поддерживаются оба формата lang файла
        $key1 = self::getNamespace() . 'enums.names.' . static::class . (is_null($code) ? '' : ('.' . $code));
        $key2 = self::getNamespace() . 'enums.' . static::class . (is_null($code) ? '' : ('.' . $code));
        if (trans()->has($key1)) {
            return $key1;
        }

        if (trans()->has($key2)) {
            return $key2;
        }

        if ($throwIfNotExists) {
            throw new EnumTransNotFoundException('Для класса ' . static::class . " не найден перевод названия $key1 или $key2");
        } else {
            return null;
        }
    }

    /**
     * @param string|int $code код элемента
     * @return string|null
     */
    private static function getTransDescId(string|int $code): ?string
    {
        $key = self::getNamespace() . 'enums.descriptions.' . static::class . '.' . $code;
        if (trans()->has($key)) {
            return $key;
        }

        return null;
    }

    /**Получить название enum из lang.enums.names.[class_name] или lang.enums.[class_name]
     * @param array $args
     * @return string
     * @throws EnumTransNotFoundException
     */
    public function trans(array $args = []): string
    {
        return trans(self::getTransNameId($this->value), $args);
    }

    /**Получить описание enum из lang.enums.descriptions.[class_name]
     * @param array $args
     * @return string|null
     * @throws EnumTransNotFoundException
     */
    public function desc(array $args = []): ?string
    {
        $key = self::getTransDescId($this->value);
        if (empty($key)) {
            return null;
        }
        return trans($key, $args);
    }

    public function render(): array
    {
        return [
            'id' => (string)$this->value,
            'name' => $this->trans(),
            'description' => $this->desc(),
        ];
    }

    /**Получение объекта перечисления по его переводу
     * @param string $translation
     * @return UnitEnum|BaseEnumTrait
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
     * @param string|null $translation
     * @return BaseEnumTrait|null
     * @throws EnumTransNotFoundException
     */
    public static function tryFromTrans(?string $translation): ?static
    {
        foreach (trans(self::getTransNameId(null, false)) as $key => $trans) {
            if ($trans === $translation) {
                return static::from($key);
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

