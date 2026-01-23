<?php

namespace Payments;

use Payments\Http\PaymentsHttpClient;

class Payments
{
    public function __construct(
        protected PaymentsHttpClient $httpClient
    ) {}

    /**
     * Get the payment driver (all actions from config).
     */
    public function driver(?string $driver = null): PaymentDriver
    {
        $driver = $driver ?? config('payments.default', 'paymob');

        return new PaymentDriver($driver, $this->httpClient);
    }

    /**
     * Alias for driver() - for backward compatibility.
     */
    public function gateway(?string $driver = null): PaymentDriver
    {
        return $this->driver($driver);
    }

    /**
     * Pay action - uses default driver from config.
     */
    public function pay(array $data, ?string $driver = null)
    {
        return $this->driver($driver)->pay($data);
    }

    /**
     * Refund action - uses default driver from config.
     */
    public function refund(array $data, ?string $driver = null)
    {
        return $this->driver($driver)->refund($data);
    }

    /**
     * Status action - uses default driver from config.
     */
    public function status(array $data, ?string $driver = null)
    {
        return $this->driver($driver)->status($data);
    }

    /**
     * Call any action from config - uses default driver.
     */
    public function action(string $actionName, array $payload = [], array $options = [], array $placeholders = [], ?string $driver = null)
    {
        return $this->driver($driver)->action($actionName, $payload, $options, $placeholders);
    }
}
