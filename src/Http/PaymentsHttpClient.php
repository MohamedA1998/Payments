<?php

namespace Payments\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PaymentsHttpClient
{
    public function __construct(protected array $config) {}

    public function request(string $driver, string $method, string $endpoint, array $options = []): \Illuminate\Http\Client\Response
    {
        $driverConfig = $this->config['drivers'][$driver] ?? [];
        $baseUrl = $driverConfig['base_url'] ?? '';
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $request = Http::timeout($driverConfig['timeout'] ?? 30);

        // Authentication
        if (isset($driverConfig['bearer'])) {
            $request->withToken($driverConfig['bearer']);
        } elseif (isset($driverConfig['basic_auth'])) {
            $request->withBasicAuth(
                $driverConfig['basic_auth']['username'],
                $driverConfig['basic_auth']['password']
            );
        }

        // Headers
        if (isset($driverConfig['headers'])) {
            $request->withHeaders($driverConfig['headers']);
        }

        // Additional options
        if (isset($options['headers'])) {
            $request->withHeaders($options['headers']);
        }

        return $request->send($method, $url, $options);
    }
}
