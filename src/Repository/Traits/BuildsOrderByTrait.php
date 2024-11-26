<?php

namespace Laravel\Foundation\Repository\Traits;

trait BuildsOrderByTrait
{
    /**настройка сортировки
     * @param array $orderBy
     * @return $this
     */
    public function orderBy(array $orderBy): static
    {
        foreach ($orderBy as $column => $direction) {
            $this->builder->orderBy($column, $direction);
        }
        return $this;
    }

}