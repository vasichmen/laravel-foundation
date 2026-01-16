<?php


namespace Laravel\Foundation\Abstracts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use Laravel\Foundation\Exceptions\NeedExtendedEnumException;
use Laravel\Foundation\Traits\Enum\BaseEnumTrait;
use UnitEnum;

abstract class AbstractResource extends JsonResource
{
    /**Рендер связи модели. Если связь не загружена, то возвращает пустой массив. Если связь - массив, то ресурс будет применен к каждому элементу
     * @param string $key поле модели или название связи, откуда брать данные
     * @param string|AbstractResource $resourceNamespace класс ресурса для сущности
     * @param callable|null $dataPreparer подготовка поля перед применением ресурса (например, отсортировать)
     * @return array
     */
    protected function getRelation(string $key, string $resourceNamespace, ?callable $dataPreparer = null): array
    {
        if (!$this->resource->isRelation($key) || !$this->resource->relationLoaded($key)) {
            return [];
        }
        $resourceData = $this->resource->getRelationValue($key);

        if ($dataPreparer) {
            $resourceData = $dataPreparer($resourceData);
        }

        $resourceKey = Str::snake($key);
        return [
            $resourceKey => match (true) {
                is_null($resourceData) => null,
                $resourceData instanceof Collection,
                    $resourceData instanceof SupportCollection,
                    is_array($resourceData) && array_is_list($resourceData)
                => $resourceNamespace::collection($resourceData),
                default => new $resourceNamespace($resourceData)
            },
        ];
    }

    /**Рендер enum. Рендерится в объект с кодом и названием. Название берется из метода trans enum.
     * @param string $key
     * @param string|null $renderIdAs тип поля id в объекте. По умолчанию тип такой же, как и тип value. Допускаются значения null, "string", "int"
     * @return array|array[]|null[]
     * @throws NeedExtendedEnumException
     */
    protected function getEnum(string $key, ?string $renderIdAs = null): array
    {
        if ($this->resource instanceof AbstractDto) {
            return [$key => $this->resource->{$key}->render()];
        }

        if (!in_array($key, $this->resource->getFillable())) {
            return [];
        }
        return $this->renderEnum($key, $this->{$key}, $renderIdAs);
    }

    /**Отрисовка enum в объект с названием и id в заданный ключ. Если первым параметром передать Enum, то вернется только массив описания Enum без ключа
     * @param string|UnitEnum $key
     * @param UnitEnum|null $enum
     * @param string|null $renderIdAs тип поля id в объекте. По умолчанию тип такой же, как и тип value. Допускаются значения null, "string", "int"
     * @return array|array[]|null[]
     * @throws NeedExtendedEnumException
     */
    protected function renderEnum(string|UnitEnum $key, null|UnitEnum $enum = null, ?string $renderIdAs = null): array
    {
        $withoutKey = $key instanceof UnitEnum;
        if ($withoutKey) {
            $enum = $key;
        }

        if (is_null($enum)) {
            return [$key => null];
        }

        if (!method_exists($enum, 'trans')) {
            throw new NeedExtendedEnumException('Объект ' . get_class($enum) . ' должен быть расширен трейтом ' . BaseEnumTrait::class);
        }

        /** @var BaseEnumTrait $enum */

        $result = $enum->render($renderIdAs);
        if ($withoutKey) {
            return $result;
        }

        return [
            $key => $result,
        ];
    }

    /**Рендер массива кодов Enum
     * @param string $field
     * @param string|UnitEnum $enumClass
     * @param string|null $renderIdAs тип поля id в объекте. По умолчанию тип такой же, как и тип value. Допускаются значения null, "string", "int"
     * @return array|array[]
     * @throws NeedExtendedEnumException
     */
    protected function getEnumArray(string $field, string $enumClass, ?string $renderIdAs = null): array
    {
        if ($this->resource instanceof AbstractModel) {
            if (!in_array($field, $this->resource->getFillable())) {
                return [];
            }
        }

        $value = $this->{$field};
        if (empty($value)) {
            return [$field => $value];
        }
        $result = [];
        foreach ($value as $id) {
            $result[] = $this->renderEnum($enumClass::from($id), $renderIdAs);
        }

        return [$field => $result];
    }


    /**Рендер даты из поля текущего объекта модели. НЕ работает с DTO
     * @param string $field
     * @return array<string=>Carbon>
     */
    protected function getDate(string $field): array
    {
        /** @var AbstractModel $this */
        if (!array_key_exists($field, $this->getAttributes())) {
            return [];
        }

        //2023-09-22T16:50:59.000000Z
        return $this->renderDate($field);
    }

    /**Рендер даты из поля текущего объекта. Работает с DTO
     * @param string $field
     * @return array|null[]
     */
    protected function renderDate(string $field): array
    {
        /** @var Carbon $date */
        $date = $this->{$field};
        if (is_null($date)) {
            return [Str::snake($field) => null];
        }

        $date = $date->setTimezone('UTC');

        return [Str::snake($field) => $date->format('Y-m-d\TH:i:s.u\Z')];
    }

    /**Рендер дат создания и обновления. Только для моделей
     * @return array{created_at:Carbon,updated_at:Carbon}
     */
    protected function renderTimestamps(): array
    {
        /** @var AbstractModel $model */
        $model = $this->resource;
        return [
            $model::CREATED_AT => $model->{$model::CREATED_AT},
            $model::UPDATED_AT => $model->{$model::UPDATED_AT},
        ];
    }

    /**Рендер списка полей. Если поле не инициализировано в модели(например, не загружено через select), то его не будет в массиве
     * @param array $fields
     * @return array
     */
    protected function getFields(array $fields): array
    {
        //для модели берем только загруженные атрибуты. Для массивов и коллекций - сам ресурс, для всего остального приводим к массиву.
        $attrs = match (true) {
            $this->resource instanceof AbstractModel => $this->resource->getAttributes(),
            $this->resource instanceof AbstractDto => $this->resource->toArray(),
            $this->resource instanceof Arrayable => $this->resource->toArray(),
            is_array($this->resource) => $this->resource,
            is_object($this->resource) => (array)$this->resource,
            default => throw new \Exception('Этот тип не реализован'),
        };

        //все переданные поля приводим к snake
        $fields = collect($fields)->map(static fn(string $field) => Str::snake($field));

        $result = [];
        foreach ($attrs as $attr => $value) {
            $attr = Str::snake($attr);
            if ($fields->contains($attr)) {
                $result[$attr] = $value;
            }
        }
        return $result;
    }
}