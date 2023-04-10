<?php


namespace Laravel\Foundation\Exceptions;

use Laravel\Foundation\Abstracts\AbstractApiException;

class UserNotAuthorizedException extends AbstractApiException
{
    public const EXCEPTION_NAME = 'user_not_authorized';
    public const EXCEPTION_CODE = 403;
}
