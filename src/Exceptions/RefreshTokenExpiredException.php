<?php


namespace Laravel\Foundation\Exceptions;

use Laravel\Foundation\Abstracts\AbstractApiException;

class RefreshTokenExpiredException extends AbstractApiException
{
    public const EXCEPTION_NAME = 'refresh_token_expired';
    public const EXCEPTION_CODE = 424;
}
