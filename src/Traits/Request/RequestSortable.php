<?php


namespace Laravel\Foundation\Traits;

use Laravel\Foundation\Abstracts\AbstractDto;

trait RequestSortable
{
    public function validated(): array|AbstractDto
    {
        $data = $this->getValidatorInstance()->validated();

        //если есть сортировка, то преобразуем в удобный формат
        if (!empty($data['sort'])) {
            $data['sort'] = collect($data['sort'])->mapWithKeys(function ($value) {
                return [$value['sort'] => $value['by']];
            })->toArray();
        }

        return $this->prepareValidatedData($data);
    }
}
