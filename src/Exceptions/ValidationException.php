<?php


namespace Laravel\Foundation\Exceptions;

use Laravel\Foundation\Abstracts\AbstractApiException;
use Laravel\Foundation\Presenters\ValidationExceptionPresenter;

class ValidationException extends AbstractApiException
{
    const EXCEPTION_NAME = 'validation';
    const EXCEPTION_CODE = 400;

    protected string $presenter = ValidationExceptionPresenter::class;
}
