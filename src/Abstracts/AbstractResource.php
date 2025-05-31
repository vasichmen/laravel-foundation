<?php


namespace Laravel\Foundation\Abstracts;

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
     * @param string $resourceNamespace класс ресурса для сущности
     * @param callable|null $dataPreparer подготовка поля перед применением ресурса (например, отсортировать)
     * @return array
     */
    public function getRelation(string $key, string $resourceNamespace, ?callable $dataPreparer = null): array
    {
        /** @var AbstractResource $resourceNamespace */

        if (!$this?->isRelation($key) || !$this?->relationLoaded($key)) {
            return [];
        }
        $resourceData = $this->getRelationValue($key);

        if ($dataPreparer) {
            $resourceData = $dataPreparer($resourceData);
        }

        $resourceKey = Str::snake($key);
        return [
            $resourceKey => match (true) {
                is_null($resourceData) => null,
                $resourceData instanceof Collection,
                    $resourceData instanceof SupportCollection,
                    is_array($resourceData) && !$this->isAssociative($resourceData)
                => $resourceNamespace::collection($resourceData),
                default => new $resourceNamespace($resourceData)
            },
        ];
    }

    private function isAssociative(array $arr): bool
    {
        return (bool)count(array_filter(array_keys($arr), "is_string")) == count($arr);
    }

    /**Рендер enum. Рендерится в объект с кодом и названием. Название берется из метода trans enum. Если К этому enum не прикреплен трейт EnumTranslatable, то вместо имени будет null;
     * @param string $key
     * @return array|array[]|null[]
     * @throws NeedExtendedEnumException
     */
    public function getEnum(string $key): array
    {
        if (!in_array($key, $this->getFillable())) {
            return [];
        }
        return $this->renderEnum($key, $this->{$key});
    }

    /**Отрисовка enum в объект с названием и id в заданный ключ. Если первым параметром передать Enum, то вернется только массив описания Enum без ключа
     * @param string|UnitEnum $key
     * @param UnitEnum|null $enum
     * @return array|array[]|null[]
     * @throws NeedExtendedEnumException
     */
    public function renderEnum(string|UnitEnum $key, ?UnitEnum $enum = null): array
    {
        /** @var BaseEnumTrait $enum */

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

        $result = $enum->render();
        if ($withoutKey) {
            return $result;
        }

        return [
            $key => $result,
        ];
    }

    /**Рендер массива кодов Enum
     * @param string $field
     * @param string $enumClass
     * @return array|array[]
     * @throws NeedExtendedEnumException
     */
    public function getEnumArray(string $field, string $enumClass): array
    {
        /** @var UnitEnum $enumClass */

        if (!in_array($field, $this->getFillable())) {
            return [];
        }

        $value = $this->{$field};
        if (empty($value)) {
            return [$field => $value];
        }
        $result = [];
        foreach ($value as $id) {
            $result[] = $this->renderEnum($enumClass::from($id));
        }

        return [$field => $result];
    }


    /**Рендер даты в одном формате
     * @param string $field
     * @return array<string=>Carbon>
     */
    public function getDate(string $field): array
    {
        /** @var AbstractModel $this */
        if (!array_key_exists($field, $this->getAttributes())) {
            return [];
        }

        /** @var Carbon $date */
        $date = $this->{$field};
        if (is_null($date)) {
            return [$field => null];
        }
        $date = $date->setTimezone('UTC');
        //2023-09-22T16:50:59.000000Z
        return [$field => $date->format('Y-m-d\TH:i:s.u\Z')];
    }

    /**Рендер дат создания и обновления
     * @return array{created_at:Carbon,updated_at:Carbon}
     */
    public function renderTimestamps(): array
    {
        /** @var AbstractModel $model */
        $model = $this->resource;
        return [
            $model::CREATED_AT => $model->{$model::CREATED_AT},
            $model::UPDATED_AT => $model->{$model::UPDATED_AT},
        ];
    }

    public function getAttribute(string $name): array
    {
        return $this->offsetExists($name)
            ? [$name => $this[$name]]
            : [];
    }

    /**Объединяет сущности в связи через запятую
     * @param string $entity название связи
     * @param string $fieldName название поля, откуда брать название сущности в связи
     * @return array
     */
    protected function getEntityListString(string $entity, string $fieldName = 'name'): array
    {
        if (!$this?->relationLoaded($entity)) {
            return [];
        }

        $entities = $this[$entity];
        if ($entities->isEmpty()) {
            return ["{$entity}_string" => ''];
        }
        return ["{$entity}_string" => $entities->pluck($fieldName)->join(', ')];
    }
}