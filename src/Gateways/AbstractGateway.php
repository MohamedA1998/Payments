<?php

namespace Payments\Gateways;

use Payments\Gateways\Contracts\PaymentGateway;
use Payments\PaymentDriver;

abstract class AbstractGateway implements PaymentGateway
{
    public function __construct(protected PaymentDriver $driver)
    {
    }

    /**
     * Helper to replace {placeholders} in endpoints.
     */
    protected function endpoint(string $template, array $placeholders = []): string
    {
        foreach ($placeholders as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }
}

