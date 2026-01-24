<?php

namespace Payments;

use Payments\Http\PaymentsHttpClient;

class PaymentDriver
{
    public function __construct(
        protected string $driver,
        protected PaymentsHttpClient $httpClient
    ) {}

    public function action(string $actionName, array $payload = [], array $options = [], array $placeholders = []): \Illuminate\Http\Client\Response
    {
        $driverConfig = config("payments.drivers.{$this->driver}", []);
        $actionConfig = $driverConfig['actions'][$actionName] ?? null;

        if (!$actionConfig) {
            throw new \InvalidArgumentException("Action [{$actionName}] not found for driver [{$this->driver}]");
        }

        $method = strtoupper($actionConfig['method'] ?? 'POST');
        $path = $actionConfig['path'] ?? '';

        // Extract placeholders from payload
        if (empty($placeholders) && isset($actionConfig['placeholders'])) {
            foreach ($actionConfig['placeholders'] as $placeholder => $key) {
                if (isset($payload[$key])) {
                    $placeholders[$placeholder] = $payload[$key];
                    unset($payload[$key]);
                }
            }
        }

        // Replace placeholders in path
        foreach ($placeholders as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }

        // Merge options
        if (isset($actionConfig['options'])) {
            $options = array_merge($actionConfig['options'], $options);
        }

        // Add payload
        if ($method === 'GET') {
            $options['query'] = ($options['query'] ?? []) + $payload;
        } else {
            $options['json'] = ($options['json'] ?? []) + $payload;
        }

        return $this->httpClient->request($this->driver, $method, $path, $options);
    }

    public function pay(array $data): \Illuminate\Http\Client\Response
    {
        return $this->action('pay', $data);
    }

    public function refund(array $data): \Illuminate\Http\Client\Response
    {
        return $this->action('refund', $data);
    }

    public function status(array $data): \Illuminate\Http\Client\Response
    {
        return $this->action('status', $data);
    }

    public function __call(string $method, array $arguments): \Illuminate\Http\Client\Response
    {
        $driverConfig = config("payments.drivers.{$this->driver}", []);
        if (isset($driverConfig['actions'][$method])) {
            return $this->action($method, $arguments[0] ?? [], $arguments[1] ?? [], $arguments[2] ?? []);
        }
        throw new \InvalidArgumentException("Method [{$method}] not found for driver [{$this->driver}]");
    }
}
