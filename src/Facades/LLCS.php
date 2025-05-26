<?php

namespace LCSLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LCSLaravel\LLCS
 */
class LLCS extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'llcs';
    }
}
