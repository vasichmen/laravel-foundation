<?php

namespace Laravel\Foundation\Repository\Traits;

trait BuildsCacheTrait
{

    /**список кастомных тегов для кэша
     * @param array $cacheTags
     * @return $this
     */
    public function cacheTags(array $cacheTags): static
    {
        $this->cacheTags = $cacheTags;
        return $this;
    }

    /**Установка кастомного ключа кэша
     * @param string|null $cacheKey
     * @return $this
     */
    public function cacheKey(?string $cacheKey): static
    {
        $this->cacheKey = $cacheKey;
        return $this;
    }

    /**Установка кастомного ключа кэша
     * @param int|bool $cacheFor
     * @return $this
     */
    public function cacheFor(int|bool $cacheFor): static
    {
        $this->cacheFor = $cacheFor;
        return $this;
    }


    protected function prepareCache(): void
    {
        if ($this->cacheFor !== false) {
            $cacheFor = $this->cacheFor === true ? config('cache.ttl') : $this->cacheFor;
            $this->builder->cacheFor($cacheFor);
            $this->builder->cacheTags($this->cacheTags);
            if ($this->cacheKey) {
                $this->builder->cacheKey($this->cacheKey);
            }
        }
    }
}