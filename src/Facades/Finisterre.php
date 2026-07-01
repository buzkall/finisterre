<?php

namespace Arzcode\Finisterre\Facades;

use Arzcode\Finisterre\FinisterrePlugin;
use Illuminate\Support\Facades\Facade;

/**
 * @see FinisterrePlugin
 */
class Finisterre extends Facade
{
    protected static function getFacadeAccessor()
    {
        return FinisterrePlugin::class;
    }
}
