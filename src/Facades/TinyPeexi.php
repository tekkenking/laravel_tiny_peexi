<?php

namespace Tekkenking\TinyPeexi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Tekkenking\TinyPeexi\Services\TinyPeexiClient
 */
class TinyPeexi extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'tinypeexi';
    }
}
