<?php


namespace Laravel\Foundation\Exceptions;


use Laravel\Foundation\Abstracts\AbstractApiException;

class EnumTransNotFoundException extends AbstractApiException
{
    const EXCEPTION_NAME = 'enum_trans_not_found';
}
