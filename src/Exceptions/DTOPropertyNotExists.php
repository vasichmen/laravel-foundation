<?php


namespace Laravel\Foundation\Exceptions;


use Laravel\Foundation\Abstracts\AbstractApiException;

class DTOPropertyNotExists extends AbstractApiException
{
    public const EXCEPTION_NAME = 'dto_property_not_exists';

}
