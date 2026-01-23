<?php

namespace Payments;

use Illuminate\Http\Client\Response;
use Payments\Http\PaymentsHttpClient;
use InvalidArgumentException;

class PaymentDriver
{
    protected string $driver;
    protected ?array $cachedConfig = null;

    public function __construct(string $driver, protected PaymentsHttpClient $httpClient)
    {
        $this->driver = $driver;
    }

    /**
     * Get driver config (cached for performance).
     */
    protected function getConfig(): array
    {
        if ($this->cachedConfig === null) {
            $this->cachedConfig = config("payments.drivers.{$this->driver}", []);
        }
        return $this->cachedConfig;
    }

    /**
     * Send an HTTP request to the configured gateway for this driver.
     */
    public function request(string $method, string $endpoint, array $options = []): Response
    {
        return $this->httpClient->request($this->driver, $method, $endpoint, $options);
    }

    /**
     * Call action defined in config file.
     * High-performance, small code, easy to optimize.
     * 
     * @param string $actionName Action name from config (e.g., 'pay', 'refund', 'status', 'custom_action')
     * @param array $payload Request payload
     * @param array $options Additional options (headers, query params, etc.) - overrides config options
     * @param array $placeholders Placeholders to replace in action path (e.g., ['id' => 123])
     */
    public function action(string $actionName, array $payload = [], array $options = [], array $placeholders = []): Response
    {
        $driverConfig = $this->getConfig();
        $actions = $driverConfig['actions'] ?? [];

        if (! isset($actions[$actionName])) {
            throw new InvalidArgumentException("Action [{$actionName}] not found in config for driver [{$this->driver}].");
        }

        $actionConfig = $actions[$actionName];
        $method = strtoupper($actionConfig['method'] ?? 'POST');
        $path = $actionConfig['path'] ?? '';

        // Auto-extract placeholders from payload if mapping defined in config
        if (empty($placeholders) && isset($actionConfig['placeholders'])) {
            foreach ($actionConfig['placeholders'] as $placeholder => $key) {
                if (isset($payload[$key])) {
                    $placeholders[$placeholder] = $payload[$key];
                    unset($payload[$key]); // Remove from payload
                }
            }
        }

        // Replace placeholders in path (e.g., /payments/{id} -> /payments/123)
        foreach ($placeholders as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }

        // Merge action-specific options from config
        if (isset($actionConfig['options'])) {
            $options = array_merge($actionConfig['options'], $options);
        }

        if ($method === 'GET') {
            $options['query'] = ($options['query'] ?? []) + $payload;
        } else {
            $options['json'] = ($options['json'] ?? []) + $payload;
        }

        return $this->request($method, $path, $options);
    }

    /**
     * Pay action - uses 'pay' action from config.
     */
    public function pay(array $data): Response
    {
        return $this->action('pay', $data);
    }

    /**
     * Refund action - uses 'refund' action from config.
     */
    public function refund(array $data): Response
    {
        return $this->action('refund', $data);
    }

    /**
     * Status action - uses 'status' action from config.
     */
    public function status(array $data): Response
    {
        return $this->action('status', $data);
    }

    /**
     * Magic method for dynamic action calls.
     * Allows: $driver->pay([...]) instead of $driver->action('pay', [...])
     */
    public function __call(string $method, array $arguments): Response
    {
        $driverConfig = $this->getConfig();
        $actions = $driverConfig['actions'] ?? [];

        if (isset($actions[$method])) {
            $payload = $arguments[0] ?? [];
            $options = $arguments[1] ?? [];
            $placeholders = $arguments[2] ?? [];
            return $this->action($method, $payload, $options, $placeholders);
        }

        throw new InvalidArgumentException("Method [{$method}] not found for driver [{$this->driver}].");
    }
}
