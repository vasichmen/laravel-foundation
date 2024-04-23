<?php


namespace Laravel\Foundation\Abstracts;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Foundation\Exceptions\DTOPropertyNotExists;
use ReflectionException;
use ReflectionObject;
use ReflectionProperty;

abstract class AbstractDto
{
    public function __construct(array $validatedData)
    {
        $this->parseData($validatedData);
    }

    /**Парсит входной массив, по умолчанию кладет значения в свойства текущего объекта (названия ключей массива преобразуются в camelCase)
     * @param array $data массив параметров из запроса
     * @param bool $throwIfNoProperty если true, то при отсутствии нужного свойства будет сгенерировано исключение
     * @return void
     * @throws DTOPropertyNotExists
     */
    protected function parseData(array $data, bool $throwIfNoProperty = true): void
    {
        foreach ($data as $param => $value) {
            $propertyName = Str::camel($param);
            $propertyExists = property_exists($this, $propertyName);

            if ($throwIfNoProperty && !$propertyExists) {
                throw new DTOPropertyNotExists("Свойство {$propertyName} не найдено в классе " . static::class);
            }

            if ($propertyExists) {

                //если пришел null, то не проверяем тип свойства
                if (is_null($value)) {
                    $this->{$propertyName} = $value;
                    continue;
                }

                $reflectionProperty = new ReflectionProperty(static::class, $propertyName);
                $propertyClass = $reflectionProperty->getType()->getName();
                $this->{$propertyName} = match (true) {
                    is_subclass_of($propertyClass, \UnitEnum::class) => $value instanceof \UnitEnum
                        ? $value
                        : $propertyClass::from($value),
                    is_subclass_of($propertyClass, CarbonInterface::class) => Carbon::parse($value),
                    $propertyClass === Collection::class => collect($value),
                    default => $value,
                };

            }
        }
    }

    /**Возвращает массив, где ключами являются названия публичных полей этого объекта в snake_case, а значениями - текущие значения этих полей<br>
     * Незаполненные свойства DTO не будут представлены в массиве
     * @return array
     * @throws ReflectionException
     */
    public function toArray(): array
    {
        $result = [];
        $reflectionObject = new ReflectionObject($this);
        $publicProperties = $reflectionObject->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($publicProperties as $publicProperty) {
            $reflectionProperty = new ReflectionProperty(static::class, $publicProperty->name);
            if ($reflectionProperty->isInitialized($this)) {
                $key = Str::snake($publicProperty->name);
                $result[$key] = $this->{$publicProperty->name};
            }
        }
        return $result;
    }
}