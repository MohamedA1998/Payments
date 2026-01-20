<?php

namespace Payments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Payments\PaymentDriver driver(?string $driver = null)
 */
class Payments extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'payments';
    }
}
