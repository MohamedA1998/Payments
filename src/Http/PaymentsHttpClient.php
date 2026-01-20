<?php

namespace Payments\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class PaymentsHttpClient
{
    public function __construct(private array $config = []) {}

    /**
     * Send an HTTP request to the configured payment gateway.
     *
     * Options:
     * - headers, query, json, form_params, multipart, body, timeout, verify, base_url
     */
    public function request(string $driver, string $method, string $endpoint, array $options = []): Response
    {
        $driverConfig = $this->getDriverConfig($driver);
        $pending = $this->buildRequest($driverConfig, $options);

        $payload = Arr::only($options, ['query', 'json', 'form_params', 'multipart', 'body']);
        $endpoint = ltrim($endpoint, '/');

        return $pending->send(strtoupper($method), $endpoint, $payload);
    }

    protected function buildRequest(array $driverConfig, array $options): PendingRequest
    {
        $request = Http::acceptJson();

        $baseUrl = $options['base_url'] ?? $driverConfig['base_url'] ?? null;
        if ($baseUrl) {
            $request = $request->baseUrl($baseUrl);
        }

        $timeout = $options['timeout'] ?? $driverConfig['timeout'] ?? null;
        if ($timeout) {
            $request = $request->timeout($timeout);
        }

        $verify = $options['verify'] ?? $driverConfig['verify'] ?? null;
        if (! is_null($verify)) {
            $request = $request->withOptions(['verify' => $verify]);
        }

        $headers = ($driverConfig['headers'] ?? []) + ($options['headers'] ?? []);

        if (! empty($driverConfig['bearer'])) {
            $headers['Authorization'] = 'Bearer ' . $driverConfig['bearer'];
        }

        if (! empty($driverConfig['basic_auth']) && is_array($driverConfig['basic_auth'])) {
            $request = $request->withBasicAuth(
                $driverConfig['basic_auth']['username'] ?? '',
                $driverConfig['basic_auth']['password'] ?? ''
            );
        }

        return $request->withHeaders($headers);
    }

    protected function getDriverConfig(string $driver): array
    {
        $drivers = $this->config['drivers'] ?? [];
        if (! isset($drivers[$driver])) {
            throw new InvalidArgumentException("Driver [{$driver}] is not configured.");
        }

        return $drivers[$driver];
    }
}
