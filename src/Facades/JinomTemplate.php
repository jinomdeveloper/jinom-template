<?php

namespace Jinom\JinomTemplate\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Jinom\JinomTemplate\JinomTemplate
 */
class JinomTemplate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Jinom\JinomTemplate\JinomTemplate::class;
    }
}
