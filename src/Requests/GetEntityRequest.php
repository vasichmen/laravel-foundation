<?php

namespace Laravel\Foundation\Requests;


use Laravel\Foundation\Abstracts\AbstractRequest;
use Laravel\Foundation\DTO\GetEntityRequestDTO;

class GetEntityRequest extends AbstractRequest
{
    protected ?string $dtoClassName = GetEntityRequestDTO::class;

    public function rules()
    {
        return [
            'id' => ['required', 'uuid']
        ];
    }
}