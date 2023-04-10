<?php


namespace Laravel\Foundation\Exceptions;


use Laravel\Foundation\Abstracts\AbstractApiException;

class InvalidTagException extends AbstractApiException
{
    public const EXCEPTION_NAME = 'invalid_tag_exception';
}