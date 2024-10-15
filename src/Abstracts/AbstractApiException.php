<?php


namespace Laravel\Foundation\Abstracts;

use Laravel\Foundation\Presenters\ApiExceptionPresenter;

abstract class AbstractApiException extends AbstractException
{
    protected string $presenter = ApiExceptionPresenter::class;

    public function render()
    {
        return (new $this->presenter([
            'name' => static::EXCEPTION_NAME,
            'http_code' => $this->code ?: static::EXCEPTION_CODE,
            'bag' => $this->data,
        ]));
    }
}
