<?php

namespace Laravel\Foundation\Repository\Traits;

use Illuminate\Support\Collection;
use Laravel\Foundation\Abstracts\AbstractModel;
use Laravel\Foundation\Cache\Builder;

trait ForwardCallsTrait
{
    /**добавляет условие where с заданным замыканием
     * @param callable $closure
     * @deprecated вместо него надо использовать обычный where
     * @return $this
     */
    public function closure(callable $closure): static
    {
        $this->builder->where($closure);
        return $this;
    }

    /**Возвращает все найденные записи из БД
     * @return Collection
     */
    public function get(): Collection
    {
        return $this->toQuery()->get();
    }

    /**Возвращает первую найденную запись или null
     * @return AbstractModel|null
     */
    public function find(): ?AbstractModel
    {
        return $this->toQuery()->take(1)->get()->first();
    }

    public function exists(): bool
    {
        return $this->toQuery()->exists();
    }

    public function count(): int
    {
        return $this->toQuery()->count();
    }

    /**Возвращает объект \Laravel\Foundation\Cache\Builder с примененными правилами
     * @return Builder
     */
    public function toQuery(): Builder
    {
        $this->preparePagination();
        $this->prepareCache();
        return $this->builder;
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$arguments);
        }

        $this->builder->{$name}(...$arguments);

        return $this;
    }
}