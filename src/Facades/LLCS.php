<?php

namespace Locospec\LLCS\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Locospec\LLCS\LLCS
 */
class LLCS extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Locospec\LLCS\LLCS::class;
    }
}
