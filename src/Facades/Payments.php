<?php

namespace Payments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Payments\PaymentDriver driver(?string $driver = null)
 * @method static \Payments\PaymentDriver gateway(?string $driver = null)
 * @method static \Illuminate\Http\Client\Response pay(array $data, ?string $driver = null)
 * @method static \Illuminate\Http\Client\Response refund(array $data, ?string $driver = null)
 * @method static \Illuminate\Http\Client\Response status(array $data, ?string $driver = null)
 * @method static \Illuminate\Http\Client\Response action(string $actionName, array $payload = [], array $options = [], array $placeholders = [], ?string $driver = null)
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
