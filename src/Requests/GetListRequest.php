<?php

namespace Laravel\Foundation\Requests;

use Laravel\Foundation\Abstracts\AbstractRequest;
use Laravel\Foundation\DTO\GetListRequestDTO;

class GetListRequest extends AbstractRequest
{
    protected ?string $dtoClassName = GetListRequestDTO::class;

    public function rules()
    {
        return [
            'q' => 'string',
            'filters' => 'array',
            'queries' => 'array',
            'properties' => 'array|nullable',
            'select' => 'array',

            ...$this->sorted(false),
            ...$this->paginated(false),
        ];
    }
}