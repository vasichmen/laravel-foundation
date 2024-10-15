<?php


namespace Laravel\Foundation\Exceptions;

use Laravel\Foundation\Abstracts\AbstractApiException;

class NeedExtendedEnumException extends AbstractApiException
{
    const EXCEPTION_NAME = 'need_extended_enum';
}
