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
    public function __construct(array|Collection $validatedData)
    {
        $this->parseData($validatedData);
    }

    /**Парсит входной массив, по умолчанию кладет значения в свойства текущего объекта (названия ключей массива преобразуются в camelCase)
     * @param array|Collection $data массив параметров из запроса
     * @param bool $throwIfNoProperty если true, то при отсутствии нужного свойства будет сгенерировано исключение
     * @return void
     * @throws DTOPropertyNotExists
     * @throws ReflectionException
     */
    protected function parseData(array|Collection $data, bool $throwIfNoProperty = true): void
    {
        foreach ($data as $param => $value) {
            $propertyName = Str::camel($param);
            $propertyExists = property_exists($this, $propertyName);

            //если свойство не найдено, то ищем связь с таким названием
            if (!$propertyExists) {
                $relationIdName = $propertyName . 'Id';
                if ($propertyExists = property_exists($this, $relationIdName)) {
                    $propertyName = $relationIdName;
                }
            }

            if ($throwIfNoProperty && !$propertyExists) {
                $message = "Свойство $propertyName или $relationIdName для параметра $param не найдено в классе " . static::class;
                throw new DTOPropertyNotExists($message);
            }

            if ($propertyExists) {

                //если пришел null, то не проверяем тип свойства
                if (is_null($value)) {
                    $this->{$propertyName} = $value;
                    continue;
                }

                //получаем тип поля
                $reflectionProperty = new ReflectionProperty(static::class, $propertyName);
                $type = $reflectionProperty->getType();
                switch (true) {
                    //одиночный тип поля
                    case $type instanceof \ReflectionNamedType:
                        $propertyClass = $type->getName();
                        switch (true) {
                            //если поле енум, то его надо распарсить
                            case is_subclass_of($propertyClass, \UnitEnum::class):
                                $this->{$propertyName} = $value instanceof \UnitEnum ? $value : $propertyClass::from($value);
                                break;
                            //если поле дата, то парсим объект Carbon
                            case is_subclass_of($propertyClass, CarbonInterface::class):
                                $this->{$propertyName} = Carbon::parse($value);
                                break;
                            //Если поле DTO, то парсим вложенный объект DTO
                            case is_subclass_of($propertyClass, AbstractDto::class):
                                $this->{$propertyName} = new $propertyClass($value);
                                break;
                            //если поле - вложенный массив или коллекция DTO, то парсим каждый элемент массива как DTO
                            case array_key_exists($propertyName, $this->getDtoArrays()):
                                $dtoClass = $this->getDtoArrays()[$propertyName];
                                $array = collect($value)
                                    ->map(static fn($item) => $item instanceof $dtoClass ? $item : new $dtoClass($item));

                                if ($propertyClass !== Collection::class) {
                                    $this->{$propertyName} = $array->toArray();
                                } else {
                                    $this->{$propertyName} = $array;
                                }
                                break;
                            //если поле коллекция, то приводим к коллекции
                            case $propertyClass === Collection::class:
                                $this->{$propertyName} = collect($value);
                                break;
                            default:
                                $this->{$propertyName} = $value;
                                break;
                        }
                        break;
                    //union - тип поля
                    case $type instanceof \ReflectionUnionType:
                        $this->{$propertyName} = $value;
                        break;
                    default:
                        throw new \Exception('Этот тип не реализован');
                }
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

            //получаем тип поля
            $reflectionProperty = new ReflectionProperty(static::class, $publicProperty->name);
            $type = $reflectionProperty->getType();

            if ($reflectionProperty->isInitialized($this)) {
                $key = Str::snake($publicProperty->name);
                switch (true) {
                    //одиночный тип поля
                    case $type instanceof \ReflectionNamedType:
                        $propertyClass = $reflectionProperty->getType()->getName();
                        switch (true) {
                            //если поле DTO, то преобразуем его в массив
                            case is_subclass_of($propertyClass, AbstractDto::class):
                                $result[$key] = $this->{$publicProperty->name}?->toArray();
                                break;
                            //если это вложенный массив DTO, то приводим каждый объект к массиву
                            case array_key_exists($publicProperty->name, $this->getDtoArrays()):
                                if (is_null($this->{$publicProperty->name})) {
                                    $result[$key] = null;
                                    break;
                                }
                                $result[$key] = [];
                                foreach ($this->{$publicProperty->name} as $item) {
                                    $result[$key][] = $item->toArray();
                                }
                                break;
                            default:
                                $result[$key] = $this->{$publicProperty->name};
                                break;
                        }
                        break;
                    //union - тип поля
                    case $type instanceof \ReflectionUnionType:
                        $result[$key] = $this->{$publicProperty->name};
                        break;
                    default:
                        throw new \Exception('Этот тип не реализован');
                }
            }
        }
        return $result;
    }

    /**Список полей-массивов DTO (массивы или коллекции DTO)
     * @return  array{string,class-string}
     */
    protected function getDtoArrays(): array
    {
        return [];
    }
}
