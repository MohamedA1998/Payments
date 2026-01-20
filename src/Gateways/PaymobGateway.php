<?php

namespace Payments\Gateways;

use Illuminate\Http\Client\Response;

class PaymobGateway extends AbstractGateway
{
    public function pay(array $data): Response
    {
        // هنا بتبني الـ payload حسب Paymob
        $payload = $data;

        return $this->driver->action('pay', $payload, [
            'method'   => 'POST',
            'endpoint' => '/acceptance/payment_keys',
        ]);
    }

    public function refund(array $data): Response
    {
        $payload = $data;

        return $this->driver->action('refund', $payload, [
            'method'   => 'POST',
            'endpoint' => '/acceptance/payments/refund',
        ]);
    }

    public function status(array $data): Response
    {
        $payload = $data;

        return $this->driver->action('status', $payload, [
            'method'   => 'GET',
            'endpoint' => '/acceptance/transactions',
        ]);
    }
}

