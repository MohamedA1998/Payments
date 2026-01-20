<?php

namespace Payments\Gateways;

use Illuminate\Http\Client\Response;

class MyFatoorahGateway extends AbstractGateway
{
    public function pay(array $data): Response
    {
        $payload = $data;

        return $this->driver->action('pay', $payload, [
            'method'   => 'POST',
            'endpoint' => '/v2/ExecutePayment',
        ]);
    }

    public function refund(array $data): Response
    {
        $payload = $data;

        return $this->driver->action('refund', $payload, [
            'method'   => 'POST',
            'endpoint' => '/v2/MakeRefund',
        ]);
    }

    public function status(array $data): Response
    {
        $payload = $data;

        return $this->driver->action('status', $payload, [
            'method'   => 'POST',
            'endpoint' => '/v2/GetPaymentStatus',
        ]);
    }
}

