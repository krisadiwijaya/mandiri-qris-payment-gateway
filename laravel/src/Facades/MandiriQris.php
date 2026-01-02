<?php

namespace MandiriQris\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class MandiriQris extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'mandiri-qris';
    }
}
