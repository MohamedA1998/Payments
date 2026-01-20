<?php

namespace Payments;

use Payments\Http\PaymentsHttpClient;
use Payments\Gateways\Contracts\PaymentGateway;
use Payments\Gateways\MyFatoorahGateway;
use Payments\Gateways\PaymobGateway;
use Payments\Gateways\PaypalGateway;
use Payments\Gateways\StripeGateway;

class Payments
{
    public function __construct(
        protected PaymentsHttpClient $httpClient
    ) {}

    /**
     * Get the default payment driver.
     */
    public function driver(?string $driver = null): PaymentDriver
    {
        $driver = $driver ?? config('payments.default');

        return new PaymentDriver($driver, $this->httpClient);
    }

    /**
     * Unified layer: get a high-level gateway instance (pay / refund / status).
     */
    public function gateway(?string $driver = null): PaymentGateway
    {
        $driverName = $driver ?? config('payments.default');
        $paymentDriver = $this->driver($driverName);

        return match ($driverName) {
            'myfatoorah' => new MyFatoorahGateway($paymentDriver),
            'paymob'     => new PaymobGateway($paymentDriver),
            'paypal'     => new PaypalGateway($paymentDriver),
            'stripe'     => new StripeGateway($paymentDriver),
            default      => new PaymobGateway($paymentDriver), // fallback بسيط، ممكن تعمله GenericGateway بعدين
        };
    }
}
