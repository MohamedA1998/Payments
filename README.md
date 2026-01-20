# Payments Laravel Package

A unified Laravel package for handling payments with multiple gateways (MyFatoorah, Paymob, PayPal, Stripe).

## Installation

Install the package via Composer:

```bash
composer require mohameda1998/payments
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=payments-config
```

This will create a `config/payments.php` file in your Laravel application.

## Usage

The package provides two levels of usage:

### 1. Unified Gateway Layer (Recommended)

Use the unified gateway interface for consistent API across all payment providers:

```php
use Payments\Facades\Payments;

// All gateways support: pay(), refund(), status()
$response = Payments::gateway('paymob')->pay([...]);
$response = Payments::gateway('myfatoorah')->refund([...]);
$response = Payments::gateway('stripe')->status([...]);
```

### 2. Low-Level Driver (Full Control)

For custom endpoints or advanced usage:

```php
$response = Payments::driver('paymob')->action('pay', $payload, [
    'method'   => 'POST',
    'endpoint' => '/acceptance/payment_keys',
]);
```

---

## Examples by Gateway

### Paymob

#### Create Payment (Pay)

```php
use Payments\Facades\Payments;

$response = Payments::gateway('paymob')->pay([
    'amount_cents' => 10000,
    'currency'      => 'EGP',
    'customer'      => [
        'first_name' => 'Ahmed',
        'last_name'  => 'Mohamed',
        'email'      => 'ahmed@example.com',
        'phone'      => '01234567890',
    ],
    'success_url'   => route('payments.success'),
    'error_url'     => route('payments.error'),
    'callback_url'  => route('payments.paymob.callback'),
    'meta'          => [
        'order_id' => $order->id,
    ],
]);

if ($response->successful()) {
    $data = $response->json();
    $redirectUrl = $data['redirect_url'] ?? null;
    
    // Redirect user to payment page
    return redirect()->away($redirectUrl);
}
```

#### Refund

```php
$response = Payments::gateway('paymob')->refund([
    'transaction_id' => $transactionId,
    'amount_cents'   => 10000,
]);
```

#### Check Payment Status

```php
$response = Payments::gateway('paymob')->status([
    'transaction_id' => $transactionId,
]);
```

---

### MyFatoorah

#### Create Payment

```php
$response = Payments::gateway('myfatoorah')->pay([
    'InvoiceValue'   => 100,
    'CustomerName'   => 'Ahmed Mohamed',
    'CustomerEmail'  => 'ahmed@example.com',
    'CustomerMobile' => '01234567890',
    'CallbackUrl'    => route('payments.myfatoorah.callback'),
    'ErrorUrl'       => route('payments.error'),
]);
```

#### Refund

```php
$response = Payments::gateway('myfatoorah')->refund([
    'Key'     => $paymentKey,
    'KeyType' => 'PaymentId',
    'Amount'  => 50,
]);
```

#### Check Payment Status

```php
$response = Payments::gateway('myfatoorah')->status([
    'Key'     => $paymentKey,
    'KeyType' => 'PaymentId',
]);
```

---

### Stripe

#### Create Payment Intent

```php
$response = Payments::gateway('stripe')->pay([
    'amount'   => 10000, // in cents
    'currency' => 'usd',
    'customer' => [
        'email' => 'ahmed@example.com',
    ],
    'metadata' => [
        'order_id' => $order->id,
    ],
]);
```

#### Refund

```php
$response = Payments::gateway('stripe')->refund([
    'payment_intent' => 'pi_1234567890',
    'amount'         => 5000,
]);
```

#### Check Payment Status

```php
$response = Payments::gateway('stripe')->status([
    'id' => 'pi_1234567890',
]);
```

---

### PayPal

#### Create Order

```php
$response = Payments::gateway('paypal')->pay([
    'intent' => 'CAPTURE',
    'purchase_units' => [
        [
            'amount' => [
                'currency_code' => 'USD',
                'value'          => '100.00',
            ],
        ],
    ],
]);
```

#### Refund

```php
$response = Payments::gateway('paypal')->refund([
    'amount' => [
        'value'         => '10.00',
        'currency_code' => 'USD',
    ],
], [
    'endpoint' => "/v2/payments/captures/{$captureId}/refund",
]);
```

---

## Handling Responses

### Success / Error Handling

```php
$response = Payments::gateway('paymob')->pay([...]);

if ($response->successful()) {
    // HTTP 2xx
    $data = $response->json();
    
    // Handle success (e.g., redirect to payment page)
    return redirect()->away($data['redirect_url']);
}

if ($response->failed()) {
    // HTTP 4xx/5xx
    \Log::error('Payment failed', [
        'gateway' => 'paymob',
        'status'  => $response->status(),
        'body'    => $response->json(),
    ]);
    
    return back()->with('error', 'Payment failed. Please try again.');
}
```

### Available Response Methods

```php
$response->successful();  // bool - HTTP 2xx
$response->failed();      // bool - HTTP 4xx/5xx
$response->status();      // int - HTTP status code
$response->json();        // array - JSON response body
$response->body();        // string - Raw response body
```

---

## Callback / Webhook Handling

Create routes in your Laravel application to handle callbacks:

### routes/web.php

```php
Route::post('/payment/callback/paymob', [PaymentCallbackController::class, 'paymob']);
Route::post('/payment/callback/myfatoorah', [PaymentCallbackController::class, 'myfatoorah']);
Route::post('/payment/callback/stripe', [PaymentCallbackController::class, 'stripe']);
```

### PaymentCallbackController

```php
use Payments\Facades\Payments;
use Illuminate\Http\Request;

class PaymentCallbackController
{
    public function paymob(Request $request)
    {
        $payload = $request->all();
        
        // Verify payment status with gateway
        $response = Payments::gateway('paymob')->status([
            'transaction_id' => $payload['obj']['id'] ?? null,
        ]);
        
        if ($response->successful()) {
            $status = $response->json();
            
            // Update your database
            if ($status['success']) {
                // Mark payment as paid
            } else {
                // Mark payment as failed
            }
        }
        
        return response()->json(['message' => 'ok']);
    }
    
    public function myfatoorah(Request $request)
    {
        $payload = $request->all();
        
        // Handle MyFatoorah callback
        $response = Payments::gateway('myfatoorah')->status([
            'Key'     => $payload['paymentId'] ?? null,
            'KeyType' => 'PaymentId',
        ]);
        
        // Update payment status in database
        return response()->json(['message' => 'ok']);
    }
}
```

---

## Using Dependency Injection

```php
use Payments\Payments;

class PaymentController
{
    public function __construct(
        protected Payments $payments
    ) {}

    public function process()
    {
        $response = $this->payments
            ->gateway('paymob')
            ->pay([
                'amount_cents' => 10000,
                'currency'     => 'EGP',
            ]);
        
        if ($response->successful()) {
            return redirect()->away($response->json()['redirect_url']);
        }
        
        return back()->with('error', 'Payment failed');
    }
}
```

---

## Environment Variables

Add these to your `.env` file:

```env
# Default driver
PAYMENT_DRIVER=paymob

# Paymob
PAYMOB_TOKEN=your_paymob_token
PAYMOB_BASE_URL=https://accept.paymob.com/api

# MyFatoorah
MYFATOORAH_TOKEN=your_myfatoorah_token
MYFATOORAH_BASE_URL=https://api.myfatoorah.com/v2

# Stripe
STRIPE_SECRET=your_stripe_secret
STRIPE_API_VERSION=2024-06-20
STRIPE_BASE_URL=https://api.stripe.com/v1

# PayPal
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_client_secret
PAYPAL_BASE_URL=https://api.paypal.com
```

---

## Advanced Usage

### Custom Action (Low-Level)

If you need full control over the request:

```php
$response = Payments::driver('paymob')->action('custom_action', [
    'custom_field' => 'value',
], [
    'method'   => 'POST',
    'endpoint' => '/acceptance/custom_endpoint',
]);
```

### Direct HTTP Request

For complete control:

```php
$response = Payments::driver('stripe')->request('POST', '/payment_intents', [
    'json' => [
        'amount'   => 10000,
        'currency' => 'usd',
    ],
]);
```

---

## License

MIT
