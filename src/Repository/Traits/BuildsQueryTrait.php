<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Support\Str;
use Laravel\Foundation\Cache\Builder;

trait BuildsQueryTrait
{

    /**Поисковый запрос
     * @param string $query поисковый запрос
     * @param array $queryableFields список столбцов для поиска
     * @return $this
     */
    public function query(string $query, array $queryableFields): static
    {
        if (empty($query) || empty($queryableFields)) {
            return $this;
        }

        $this->builder->where(function (Builder $builder) use ($query, $queryableFields) {
            foreach ($queryableFields as $queryableField) {
                //если поле это связь, то делаем вложенный запрос
                if (Str::contains($queryableField, '.')) {
                    $builder->orWhereHas(Str::camel(Str::before($queryableField, '.')),
                        function (Builder $builder) use ($query, $queryableField) {
                            $this->setNestedQueryFilter($builder, Str::after($queryableField, '.'), $query);
                        });
                } //если простое поле, то добавляем условие
                else {
                    $builder->orWhere($queryableField, 'ilike', "%$query%");
                }
            }
        });


        return $this;
    }

    private function setNestedQueryFilter(Builder $builder, string $field, string $query): void
    {
        //если это поиск по вложенной связи, то добавляем подзапрос
        if (Str::contains($field, '.')) {
            $builder->whereHas(Str::before($field, '.'), function (Builder $builder) use ($query, $field) {
                $this->setNestedQueryFilter($builder, Str::after($field, '.'), $query);
            });
            return;
        }

        //если это уже поле модели, то применяем фильтр
        $builder->where($field, 'ilike', "%$query%");
    }
}