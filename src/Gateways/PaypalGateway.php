<?php

namespace Payments\Gateways;

use Illuminate\Http\Client\Response;

class PaypalGateway extends AbstractGateway
{
    public function pay(array $data): Response
    {
        $payload = $data;

        return $this->driver->action('pay', $payload, [
            'method'   => 'POST',
            'endpoint' => '/v2/checkout/orders',
        ]);
    }

    public function refund(array $data): Response
    {
        $payload = $data;

        $captureId = $data['capture_id'] ?? null;

        return $this->driver->action('refund', $payload, [
            'method'   => 'POST',
            'endpoint' => "/v2/payments/captures/{$captureId}/refund",
        ]);
    }

    public function status(array $data): Response
    {
        $orderId = $data['order_id'] ?? null;

        return $this->driver->action('status', [], [
            'method'   => 'GET',
            'endpoint' => "/v2/checkout/orders/{$orderId}",
        ]);
    }
}

