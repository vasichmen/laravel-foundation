<?php


namespace Laravel\Foundation\Presenters;

use Laravel\Foundation\Abstracts\AbstractPresenter;

class DataResultPresenter extends AbstractPresenter
{
    protected function resolve()
    {
        return [
            'error' => false,
            'content' => $this->resource,
        ];
    }
}
