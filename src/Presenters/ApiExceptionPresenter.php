<?php


namespace Laravel\Foundation\Presenters;

use Laravel\Foundation\Abstracts\AbstractPresenter;

class ApiExceptionPresenter extends AbstractPresenter
{
    protected function resolve()
    {
        $errorBag = [];
        $resourceErrorBag = $this->resource['bag'];

        if (is_string($resourceErrorBag)) {
            $errorMessage = $resourceErrorBag;
        }
        else {
            $errorMessage = trans('exceptions.' . $this->resource['name']);
        }

        if (is_array($resourceErrorBag)) {
            foreach ($resourceErrorBag as $item) {
                $errorBag[] = [
                    'messages' => [
                        $item,
                    ],
                ];
            }
        }

        return [
            'error' => true,
            'errorCode' => $this->resource['name'] ?? null,
            'errorMessage' => $errorMessage,
            'errorBag' => $errorBag,
        ];
    }
}
