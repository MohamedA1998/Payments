<?php

namespace Payments;

use Illuminate\Http\Client\Response;
use Payments\Http\PaymentsHttpClient;
use InvalidArgumentException;

class PaymentDriver
{
    protected string $driver;

    public function __construct(string $driver, protected PaymentsHttpClient $httpClient)
    {
        $this->driver = $driver;
    }

    /**
     * Send an HTTP request to the configured gateway for this driver.
     */
    public function request(string $method, string $endpoint, array $options = []): Response
    {
        return $this->httpClient->request($this->driver, $method, $endpoint, $options);
    }

    /**
     * Generic action: pay / refund / status / ... controlled by caller.
     */
    public function action(string $action, array $payload = [], array $options = []): Response
    {
        $method = strtoupper($options['method'] ?? 'POST');
        $endpoint = $options['endpoint'] ?? null;

        if (! $endpoint) {
            throw new InvalidArgumentException("Endpoint is required for action [{$action}] on driver [{$this->driver}].");
        }

        unset($options['endpoint'], $options['method']);

        if ($method === 'GET') {
            $options['query'] = ($options['query'] ?? []) + $payload;
        } else {
            $options['json'] = ($options['json'] ?? []) + $payload;
        }

        return $this->request($method, $endpoint, $options);
    }
}
