<?php


namespace Laravel\Foundation\Exceptions;


use Laravel\Foundation\Abstracts\AbstractApiException;

class InvalidCacheTagException extends AbstractApiException
{
    public const EXCEPTION_NAME = 'invalid_cache_tag_exception';
}