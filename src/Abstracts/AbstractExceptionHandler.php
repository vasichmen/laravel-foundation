<?php


namespace Laravel\Foundation\Abstracts;

use Illuminate\Foundation\Exceptions\Handler;
use Laravel\Foundation\Traits\Logger;
use Throwable;

abstract class AbstractExceptionHandler extends Handler
{
    use Logger;

    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof AbstractApiException) {
            return parent::render($request, $e);
        }

        $errorBag = [
            $this->formatLogMessage('%s', $e),
        ];
        $apiException = new class($errorBag) extends AbstractApiException {
        };
        return parent::render($request, $apiException);
    }
}
