<?php


namespace Laravel\Foundation\Abstracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use Laravel\Foundation\Exceptions\NeedTranslatableEnumException;

abstract class AbstractResource extends JsonResource
{
    /**Рендер связи модели. Если связь не загружена, то возвращает пустой массив. Если связь - массив, то ресурс будет применен к каждому элементу
     * @param string $key поле модели или название связи, откуда брать данные
     * @param string $resourceNamespace класс ресурса для сущности
     * @param callable|null $dataPreparer подготовка поля перед применением ресурса (например, отсортировать)
     * @return array
     */
    public function getRelation(string $key, string $resourceNamespace, callable $dataPreparer = null): array
    {
        $isLoaded = (bool)$this?->relationLoaded($key);
        if (!$isLoaded) {
            return [];
        }
        $resourceData = $this->{$key};

        if ($dataPreparer) {
            $resourceData = $dataPreparer($resourceData);
        }

        $resourceKey = Str::snake($key);
        return [
            $resourceKey => match (true) {
                $resourceData instanceof Collection,
                    $resourceData instanceof SupportCollection,
                    is_array($resourceData) && !$this->isAssociative($resourceData)
                => $resourceNamespace::collection($resourceData),
                default => new $resourceNamespace($this->{$key})
            },
        ];
    }

    /**Рендер enum. Рендерится в объект с кодом и названием. Название берется из метода trans enum. Если К этому enum не прикреплен трейт EnumTranslatable, то вместо имени будет null;
     * @param string $key
     * @return array|array[]|null[]
     * @throws NeedTranslatableEnumException
     */
    public function getEnum(string $key): array
    {
        if (!in_array($key, $this->getFillable())) {
            return [];
        }
        return $this->renderEnum($key, $this->{$key});
    }

    public function renderEnum(string $key, $enum): array
    {
        if (is_null($enum)) {
            return [$key => null];
        }
        if (!method_exists($enum, 'trans')) {
            throw new NeedTranslatableEnumException('Объект должен быть расширен трейтом EnumTranslatable: ' . get_class($enum));
        }
        return [
            $key => [
                'id' => $enum->value,
                'name' => $enum->trans(),
            ],
        ];
    }


    /**Рендер даты в одном формате
     * @param string $field
     * @return array<string=>Carbon>
     */
    public function getDate(string $field): array
    {
        $date = $this->{$field};
        if (empty($date)) {
            return [];
        }
        return [$field => \Illuminate\Support\Carbon::parse($date)];
    }

    private function isAssociative(array $arr): bool
    {
        return (bool)count(array_filter(array_keys($arr), "is_string")) == count($arr);
    }

    public function getAttribute(string $name): array
    {
        return $this->offsetExists($name)
            ? [$name => $this[$name]]
            : [];
    }

    /**Объединяет сущности в связи через запятую
     * @param $entity string название связи
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
