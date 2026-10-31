<?php

namespace Laravel\Foundation\Traits\Enum;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
     * @param UnitEnum|string|int|null $code Если null, то возвращает id всего блока для этого Enum
     * @param bool $throwIfNotExists
     * @return string|null
     * @throws EnumTransNotFoundException
     */
    private static function getTransNameId(null|int|string|UnitEnum $code, bool $throwIfNotExists = true): ?string
    {
        $value = $code instanceof UnitEnum ? $code->value : $code;
        $name = $code instanceof UnitEnum ? $code->name : null;

        //для обратной совместимости поддерживаются оба формата lang файла
        $keys = [
            self::getNamespace() . 'enums.names.' . static::class . (is_null($value) ? '' : ('.' . $value)),
            self::getNamespace() . 'enums.' . static::class . (is_null($value) ? '' : ('.' . $value)),
        ];

        //если есть возможность, ищем перевод также по имени кейса enum
        if (!is_null($name)) {
            $keys[] = self::getNamespace() . 'enums.names.' . static::class . '.' . $name;
            $keys[] = self::getNamespace() . 'enums.' . static::class . '.' . $name;
        }

        foreach ($keys as $key) {
            if (trans()->has($key)) {
                return $key;
            }
        }

        if ($throwIfNotExists) {
            throw new EnumTransNotFoundException('Для класса ' . static::class . " не найден перевод названия по ключам: " . implode(', ', $keys));
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
        return trans(self::getTransNameId($this), $args);
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

    /**Разворачивает енум в массив с полями id,name,description
     * @param string|null $renderIdAs тип поля id в объекте. По умолчанию тип такой же, как и тип value. Допускаются значения null, "string", "int"
     * @return array
     * @throws EnumTransNotFoundException
     */
    public function render(?string $renderIdAs = null): array
    {
        if (is_null($renderIdAs)) {
            $renderIdAs = self::getIdTypeForRender();
        }

        $id = match ($renderIdAs) {
            null => $this->value,
            'string' => (string)$this->value,
            'int' => (int)$this->value,
        };

        return [
            'id' => $id,
            'name' => $this->trans(),
            ...($this->desc() ? ['description' => $this->desc()] : []),
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
            if (Str::lower($trans) === Str::lower($translation)) {
                return static::tryFrom($key) ?? static::{$key} ?? null;
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

    /**Тип ID для функции render по умолчанию
     * @return ?string
     */
    protected static function getIdTypeForRender(): ?string
    {
        return null;
    }

}
