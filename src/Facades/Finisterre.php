<?php

namespace Buzkall\Finisterre\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Buzkall\Finisterre\FinisterrePlugin
 */
class Finisterre extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Buzkall\Finisterre\FinisterrePlugin::class;
    }
}
