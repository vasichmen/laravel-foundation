<?php


namespace Laravel\Foundation\Abstracts;

abstract class AbstractException extends \Exception
{
    const EXCEPTION_NAME = "internal_error";
    const EXCEPTION_CODE = 500;

    protected mixed $data = null;
    protected $code = 0;

    public function __construct(mixed $data = null, int $code = 0, ?\Throwable $previous = null)
    {
        $this->data = $data;
        $this->code = $code ?: static::EXCEPTION_CODE;

        parent::__construct(
            static::EXCEPTION_NAME,
            $code,
            $previous
        );
    }

    public function getMessageData()
    {
        return $this->data;
    }

    public function getHttpCode()
    {
        return $this->code;
    }

    public function setHttpCode(int $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getExceptionName(): string
    {
        return static::EXCEPTION_NAME;
    }
}
