<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Pagination\LengthAwarePaginator;

trait BuildsPaginationTrait
{

    /**Число записей на странице
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        $this->builder->limit($limit);
        return $this;
    }

    /**Номер страницы
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        $this->builder->offset($offset);
        return $this;
    }

    /**Возвращает пагинатор с выбранной страницей
     * @return LengthAwarePaginator
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->toQuery()->paginate(perPage: $this->limit, page: $this->offset);
    }

    protected function preparePagination(): void
    {
        if (empty($this->limit || empty($this->offset))) {
            return;
        }
        $this->builder->forPage($this->offset, $this->limit);
    }
}