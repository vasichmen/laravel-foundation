<?php

namespace Laravel\Foundation\Repository;

use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;
use Laravel\Foundation\Abstracts\AbstractModel;

abstract class PivotModel extends AbstractModel
{
    use AsPivot;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**Disable uuids
     * @var bool
     */
    protected static bool $fillsUuid = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = [];
}