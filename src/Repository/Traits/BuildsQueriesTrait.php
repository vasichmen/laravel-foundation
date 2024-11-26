<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Support\Str;
use Laravel\Foundation\Cache\Builder;
use Laravel\Foundation\Traits\BaseEnumTrait;

trait BuildsQueriesTrait
{

    /**Подставляет поиски по отдельным полям. Ключом передается название поля или отношения, значение - поисковый запрос. В названии поля отношение отделяются друг от друга точкой.
     * @param array $queries
     * @return $this
     */
    public function queries(array $queries): static
    {
        foreach ($queries as $field => $query) {
            if (empty($query)) {
                continue;
            }

            $this->setNestedQueriesFilter($this->builder, $field, $query);
        }

        return $this;
    }

    private function setNestedQueriesFilter(Builder $builder, string $field, mixed $query): void
    {
        //если это свзь, то делаем подзапрос
        if (Str::contains($field, '.')) {
            $builder->whereHas(Str::camel(Str::before($field, '.')), function (Builder $builder) use ($query, $field) {
                $this->setNestedQueriesFilter($builder, Str::after($field, '.'), $query);
            });
            return;
        }
        $this->injectQueryPart($builder, $field, $query);
    }

    private function injectQueryPart(Builder $builder, string $field, string $query): void
    {
        $fields = explode('|', $field);

        $builder->where(function (Builder $builder) use ($query, $fields) {
            foreach ($fields as $fieldName) {
                $casts = $builder->getModel()->getCasts();
                switch (true) {
                    //если это поле enum, то фильтруем все значения этого енума по переданной строке и потом передаем в запрос
                    case array_key_exists($fieldName, $casts):
                        /** @var \UnitEnum|BaseEnumTrait $enumClass */
                        $enumClass = $casts[$fieldName];

                        $ids = $enumClass::list()
                            ->filter(static fn($i) => Str::contains($i['name'], $query, true))
                            ->pluck('id');
                        $builder->orWhereIn($fieldName, $ids->toArray());
                        break;
                    default:
                        $builder->orWhere($fieldName, 'ilike', "%$query%");
                        break;
                }
            }
        });
    }
}