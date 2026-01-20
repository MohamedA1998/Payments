<?php

namespace Payments\Gateways\Contracts;

use Illuminate\Http\Client\Response;

interface PaymentGateway
{
    /**
     * Create / execute a payment (direct or link depending on gateway).
     *
     * Expected keys (convention, can be extended):
     * - amount: int|float
     * - currency: string
     * - customer: array (name, email, phone, ... )
     * - success_url, error_url, callback_url: string|null
     * - meta: array
     */
    public function pay(array $data): Response;

    /**
     * Refund an existing payment.
     *
     * Expected keys:
     * - reference / transaction_id / payment_intent / capture_id ...
     * - amount: int|float|null
     * - meta: array
     */
    public function refund(array $data): Response;

    /**
     * Check status of a payment.
     *
     * Expected keys:
     * - reference / transaction_id / payment_intent / order_id ...
     * - meta: array
     */
    public function status(array $data): Response;
}

