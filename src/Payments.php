<?php

namespace Payments;

use Payments\Http\PaymentsHttpClient;

class Payments
{
    public function __construct(protected PaymentsHttpClient $httpClient) {}

    public function driver(?string $driver = null): PaymentDriver
    {
        $driver = $driver ?? config('payments.default', 'myfatoorah');
        return new PaymentDriver($driver, $this->httpClient);
    }

    public function gateway(?string $driver = null): PaymentDriver
    {
        return $this->driver($driver);
    }

    public function pay(array $data, ?string $driver = null): \Illuminate\Http\Client\Response
    {
        return $this->driver($driver)->pay($data);
    }

    public function refund(array $data, ?string $driver = null): \Illuminate\Http\Client\Response
    {
        return $this->driver($driver)->refund($data);
    }

    public function status(array $data, ?string $driver = null): \Illuminate\Http\Client\Response
    {
        return $this->driver($driver)->status($data);
    }

    public function action(string $actionName, array $payload = [], array $options = [], array $placeholders = [], ?string $driver = null): \Illuminate\Http\Client\Response
    {
        return $this->driver($driver)->action($actionName, $payload, $options, $placeholders);
    }
}
