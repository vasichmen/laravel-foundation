<?php


namespace Laravel\Foundation\Exceptions;

use Laravel\Foundation\Abstracts\AbstractApiException;

class NeedTranslatableEnumException extends AbstractApiException
{
    const EXCEPTION_NAME = 'need_translatable_enum';
}
