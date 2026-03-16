<?php

namespace Buzkall\Finisterre\Facades;

use Buzkall\Finisterre\FinisterrePlugin;
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
