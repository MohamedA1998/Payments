<?php

namespace Payments\Gateways;

use Illuminate\Http\Client\Response;

class StripeGateway extends AbstractGateway
{
    public function pay(array $data): Response
    {
        $payload = $data;

        return $this->driver->action('pay', $payload, [
            'method'   => 'POST',
            'endpoint' => '/payment_intents',
        ]);
    }

    public function refund(array $data): Response
    {
        $payload = $data;

        return $this->driver->action('refund', $payload, [
            'method'   => 'POST',
            'endpoint' => '/refunds',
        ]);
    }

    public function status(array $data): Response
    {
        $id = $data['id'] ?? null;

        return $this->driver->action('status', [], [
            'method'   => 'GET',
            'endpoint' => "/payment_intents/{$id}",
        ]);
    }
}

