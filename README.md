# Payments Laravel Package

A unified Laravel package for handling payments with multiple gateways (MyFatoorah, Paymob, PayPal, Stripe). All endpoints are defined in config file - no need for separate gateway classes!

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [MyFatoorah Integration](#myfatoorah-integration)
- [Paymob Integration](#paymob-integration)
- [Usage Methods](#usage-methods)
- [Handling Responses](#handling-responses)
- [Callback / Webhook Handling](#callback--webhook-handling)
- [Advanced Usage](#advanced-usage)

---

## Installation

Install the package via Composer:

```bash
composer require mohameda1998/payments
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=payments-config
```

This will create a `config/payments.php` file in your Laravel application.

---

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Default payment driver
PAYMENT_DRIVER=myfatoorah

# MyFatoorah Configuration
MYFATOORAH_BASE_URL=https://apitest.myfatoorah.com
MYFATOORAH_TOKEN=your_myfatoorah_token_here

# Paymob Configuration
PAYMOB_BASE_URL=https://accept.paymob.com/api
PAYMOB_TOKEN=your_paymob_token_here

# Stripe Configuration
STRIPE_BASE_URL=https://api.stripe.com
STRIPE_SECRET=your_stripe_secret_key
STRIPE_API_VERSION=2024-06-20

# PayPal Configuration
PAYPAL_BASE_URL=https://api.paypal.com
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_client_secret
```

### Config File Structure

All payment actions are defined in `config/payments.php`. Each driver has:
- `base_url`: API base URL
- `bearer` or `basic_auth`: Authentication credentials
- `headers`: Custom headers
- `timeout`: Request timeout
- `actions`: All available endpoints (pay, refund, status, custom actions)

---

## Quick Start

### Method 1: Helper Functions (Simplest)

```php
use Payments\Facades\Payments;

// Uses default driver from config
$response = Payments::pay([...]);
$response = Payments::refund([...]);
$response = Payments::status([...]);

// Or specify driver
$response = Payments::pay([...], 'myfatoorah');
```

### Method 2: Driver/Gateway

```php
$response = Payments::driver('myfatoorah')->pay([...]);
$response = Payments::gateway('myfatoorah')->refund([...]);
```

### Method 3: Custom Actions from Config

```php
// Any action defined in config
$response = Payments::action('custom_action', [...]);
$response = Payments::driver('myfatoorah')->custom_action([...]);
```

---

## MyFatoorah Integration

### Setup

#### 1. Environment Configuration

Add to your `.env` file:

```env
PAYMENT_DRIVER=myfatoorah
MYFATOORAH_BASE_URL=https://apitest.myfatoorah.com
MYFATOORAH_TOKEN=your_test_token_here
```

**Important URLs:**
- **Test Environment**: `https://apitest.myfatoorah.com`
- **Live Environment**: `https://api.myfatoorah.com`

#### 2. Config File

The MyFatoorah configuration in `config/payments.php`:

```php
'myfatoorah' => [
    'base_url' => env('MYFATOORAH_BASE_URL', 'https://apitest.myfatoorah.com'),
    'bearer' => env('MYFATOORAH_TOKEN'),
    'headers' => [
        'Content-Type' => 'application/json',
    ],
    'timeout' => 30,
    'actions' => [
        'pay' => [
            'method' => 'POST',
            'path' => '/v2/ExecutePayment',
        ],
        'refund' => [
            'method' => 'POST',
            'path' => '/v2/MakeRefund',
        ],
        'status' => [
            'method' => 'GET',
            'path' => '/v2/GetPaymentStatus',
        ],
    ],
],
```

### Create Payment (Pay)

#### Complete Example

```php
use Payments\Facades\Payments;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $response = Payments::driver('myfatoorah')->pay([
            'InvoiceValue'   => 100.00,              // Payment amount
            'CurrencyIso'    => 'KWD',              // Currency code (KWD, SAR, AED, etc.)
            'CustomerName'   => 'Ahmed Mohamed',     // Customer full name
            'CustomerEmail'  => 'ahmed@example.com', // Customer email
            'CustomerMobile' => '01234567890',       // Customer mobile number
            'CustomerReference' => 'ORD-12345',      // Your order reference
            'DisplayCurrencyIso' => 'KWD',           // Display currency
            'CallbackUrl'    => route('payments.myfatoorah.callback'), // Success callback
            'ErrorUrl'       => route('payments.error'),                // Error callback
            'Language'       => 'en',                // Language (en, ar)
            'InvoiceItems'  => [                    // Optional: Invoice items
                [
                    'ItemName'  => 'Product Name',
                    'Quantity'  => 1,
                    'UnitPrice' => 100.00,
                ],
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            // Check if payment was successful
            if ($data['IsSuccess'] ?? false) {
                $paymentUrl = $data['Data']['InvoiceURL'] ?? null;
                $invoiceId = $data['Data']['InvoiceId'] ?? null;
                
                // Redirect user to payment page
                return redirect()->away($paymentUrl);
            }
        }

        // Handle error
        return back()->with('error', 'Payment creation failed');
    }
}
```

#### Response Structure

```php
// Success Response
{
    "IsSuccess": true,
    "Message": "Invoice created successfully",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": 123456,
        "InvoiceURL": "https://myfatoorah.com/invoice/123456",
        "CustomerReference": "ORD-12345",
        "UserDefinedField": null,
        "RecurringId": null,
        "PaymentGatewayId": 0,
        "PaymentURL": "https://myfatoorah.com/pay/123456"
    }
}

// Error Response
{
    "IsSuccess": false,
    "Message": "Validation error",
    "ValidationErrors": [
        {
            "Name": "InvoiceValue",
            "Error": "Invoice value is required"
        }
    ],
    "Data": null
}
```

### Refund Payment

#### Complete Example

```php
public function refundPayment(Request $request)
{
    $paymentKey = $request->input('payment_key'); // Payment ID from MyFatoorah
    
    $response = Payments::driver('myfatoorah')->refund([
        'Key'     => $paymentKey,           // Payment ID or Invoice ID
        'KeyType' => 'PaymentId',           // PaymentId or InvoiceId
        'Amount'  => 50.00,                  // Refund amount (optional, full refund if not provided)
        'Comment' => 'Customer requested refund', // Optional comment
    ]);

    if ($response->successful()) {
        $data = $response->json();
        
        if ($data['IsSuccess'] ?? false) {
            // Refund successful
            $refundId = $data['Data']['RefundId'] ?? null;
            
            // Update your database
            // ...
            
            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund_id' => $refundId,
            ]);
        }
    }

    return response()->json([
        'success' => false,
        'message' => 'Refund failed',
    ], 400);
}
```

#### Response Structure

```php
// Success Response
{
    "IsSuccess": true,
    "Message": "Refund processed successfully",
    "Data": {
        "RefundId": 789012,
        "RefundedValue": 50.00,
        "InvoiceId": 123456,
        "Comment": "Customer requested refund"
    }
}
```

### Check Payment Status

#### Complete Example

```php
public function checkPaymentStatus(Request $request)
{
    $paymentKey = $request->input('payment_key');
    
    $response = Payments::driver('myfatoorah')->status([
        'Key'     => $paymentKey,   // Payment ID or Invoice ID
        'KeyType' => 'PaymentId',   // PaymentId or InvoiceId
    ]);

    if ($response->successful()) {
        $data = $response->json();
        
        if ($data['IsSuccess'] ?? false) {
            $paymentData = $data['Data'] ?? [];
            $invoiceStatus = $paymentData['InvoiceStatus'] ?? null;
            $paymentStatus = $paymentData['PaymentStatus'] ?? null;
            
            // Payment statuses:
            // - Paid: Payment completed
            // - Pending: Payment pending
            // - Failed: Payment failed
            // - Cancelled: Payment cancelled
            
            return response()->json([
                'status' => $paymentStatus,
                'invoice_status' => $invoiceStatus,
                'data' => $paymentData,
            ]);
        }
    }

    return response()->json([
        'success' => false,
        'message' => 'Failed to get payment status',
    ], 400);
}
```

#### Response Structure

```php
// Success Response
{
    "IsSuccess": true,
    "Data": {
        "InvoiceId": 123456,
        "InvoiceStatus": "Paid",
        "PaymentStatus": "Paid",
        "InvoiceValue": 100.00,
        "InvoiceDisplayValue": "100.000 KWD",
        "InvoiceTransactions": [
            {
                "TransactionId": 789012,
                "PaymentId": 345678,
                "PaymentGateway": "Visa/Master",
                "PaymentDate": "2024-01-15T10:30:00",
                "PaymentValue": 100.00,
                "PaymentStatus": "Paid"
            }
        ],
        "CustomerName": "Ahmed Mohamed",
        "CustomerEmail": "ahmed@example.com",
        "CustomerMobile": "01234567890"
    }
}
```

### MyFatoorah Callback Handling

#### 1. Create Route

In `routes/web.php`:

```php
Route::post('/payment/callback/myfatoorah', [PaymentCallbackController::class, 'myfatoorah'])
    ->name('payments.myfatoorah.callback');
```

#### 2. Handle Callback

```php
use Payments\Facades\Payments;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    public function myfatoorah(Request $request)
    {
        $payload = $request->all();
        
        // MyFatoorah sends payment data in the request
        $paymentId = $payload['paymentId'] ?? $payload['PaymentId'] ?? null;
        
        if (!$paymentId) {
            return response()->json(['message' => 'Invalid callback'], 400);
        }
        
        // Verify payment status with MyFatoorah
        $response = Payments::driver('myfatoorah')->status([
            'Key'     => $paymentId,
            'KeyType' => 'PaymentId',
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            
            if ($data['IsSuccess'] ?? false) {
                $paymentData = $data['Data'] ?? [];
                $paymentStatus = $paymentData['PaymentStatus'] ?? null;
                
                // Update your database based on status
                if ($paymentStatus === 'Paid') {
                    // Mark payment as paid
                    // Update order status
                    // Send confirmation email
                    // ...
                } elseif ($paymentStatus === 'Failed') {
                    // Mark payment as failed
                    // Notify customer
                    // ...
                }
            }
        }
        
        // Always return success to MyFatoorah
        return response()->json(['message' => 'ok']);
    }
}
```

---

## Paymob Integration

### Setup

#### 1. Environment Configuration

Add to your `.env` file:

```env
PAYMENT_DRIVER=paymob
PAYMOB_BASE_URL=https://accept.paymob.com/api
PAYMOB_TOKEN=your_paymob_token_here
```

**Important URLs:**
- **API Base URL**: `https://accept.paymob.com/api`

#### 2. Config File

The Paymob configuration in `config/payments.php`:

```php
'paymob' => [
    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'),
    'bearer' => env('PAYMOB_TOKEN'),
    'headers' => [
        'Content-Type' => 'application/json',
    ],
    'timeout' => 30,
    'actions' => [
        'pay' => [
            'method' => 'POST',
            'path' => '/acceptance/payment_keys',
        ],
        'refund' => [
            'method' => 'POST',
            'path' => '/acceptance/payments/refund',
        ],
        'status' => [
            'method' => 'GET',
            'path' => '/acceptance/transactions',
        ],
    ],
],
```

### Create Payment (Pay)

#### Complete Example

```php
use Payments\Facades\Payments;

class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $response = Payments::driver('paymob')->pay([
            'auth_token'     => env('PAYMOB_TOKEN'), // Your Paymob API token
            'amount_cents'   => 10000,                // Amount in cents (100.00 EGP = 10000)
            'currency'       => 'EGP',                // Currency code
            'expiration'     => 3600,                 // Payment expiration in seconds
            'order_id'       => 'ORD-12345',          // Your order ID
            'billing_data'   => [                     // Customer billing information
                'apartment'     => '803',
                'email'         => 'ahmed@example.com',
                'floor'         => '42',
                'first_name'    => 'Ahmed',
                'street'        => '123 Main St',
                'building'      => 'Building 1',
                'phone_number'  => '01234567890',
                'shipping_method' => 'PKG',
                'postal_code'  => '12345',
                'city'          => 'Cairo',
                'country'       => 'EG',
                'last_name'     => 'Mohamed',
                'state'         => 'Cairo',
            ],
            'integration_id' => env('PAYMOB_INTEGRATION_ID'), // Your integration ID
            'lock_order_when_paid' => false,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $token = $data['token'] ?? null;
            
            if ($token) {
                // Redirect to Paymob payment page
                $paymentUrl = "https://accept.paymob.com/api/acceptance/payment_keys/{$token}";
                return redirect()->away($paymentUrl);
            }
        }

        return back()->with('error', 'Payment creation failed');
    }
}
```

#### Response Structure

```php
// Success Response
{
    "token": "ZXlKaGJHY2lPaUpJVXpVeE1pSXNJblI1Y0NJNklrcFhWQ0o5...",
    "order_id": 123456789
}

// Error Response
{
    "detail": "Authentication credentials were not provided."
}
```

### Refund Payment

#### Complete Example

```php
public function refundPayment(Request $request)
{
    $transactionId = $request->input('transaction_id');
    $amountCents = $request->input('amount_cents'); // Optional, full refund if not provided
    
    $response = Payments::driver('paymob')->refund([
        'auth_token'      => env('PAYMOB_TOKEN'),
        'transaction_id'  => $transactionId,
        'amount_cents'    => $amountCents, // Optional: partial refund
    ]);

    if ($response->successful()) {
        $data = $response->json();
        
        // Check if refund was successful
        if (isset($data['is_refunded']) && $data['is_refunded']) {
            // Refund successful
            // Update your database
            // ...
            
            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
            ]);
        }
    }

    return response()->json([
        'success' => false,
        'message' => 'Refund failed',
    ], 400);
}
```

#### Response Structure

```php
// Success Response
{
    "is_refunded": true,
    "refunded_amount_cents": 10000,
    "transaction_id": 123456789
}

// Error Response
{
    "detail": "Transaction not found"
}
```

### Check Payment Status

#### Complete Example

```php
public function checkPaymentStatus(Request $request)
{
    $transactionId = $request->input('transaction_id');
    
    $response = Payments::driver('paymob')->status([
        'auth_token'     => env('PAYMOB_TOKEN'),
        'transaction_id' => $transactionId,
    ]);

    if ($response->successful()) {
        $data = $response->json();
        
        // Transaction statuses:
        // - success: Payment completed
        // - pending: Payment pending
        // - failed: Payment failed
        
        $status = $data['success'] ?? false;
        $transactionData = $data['obj'] ?? [];
        
        return response()->json([
            'success' => $status,
            'data' => $transactionData,
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Failed to get payment status',
    ], 400);
}
```

#### Response Structure

```php
// Success Response
{
    "success": true,
    "obj": {
        "id": 123456789,
        "pending": false,
        "amount_cents": 10000,
        "currency": "EGP",
        "success": true,
        "is_auth": false,
        "is_capture": false,
        "is_standalone_payment": true,
        "is_voided": false,
        "is_refunded": false,
        "is_3d_secure": false,
        "integration_id": 123456,
        "profile_id": 789012,
        "has_parent_transaction": false,
        "order": {
            "id": 345678,
            "created_at": "2024-01-15T10:30:00",
            "delivery_needed": false,
            "merchant": {
                "id": 123,
                "created_at": "2023-01-01T00:00:00",
                "phones": ["01234567890"],
                "company_emails": ["info@example.com"],
                "company_name": "My Company",
                "state": "active",
                "country": "EG",
                "city": "Cairo",
                "postal_code": "12345",
                "street": "123 Main St"
            },
            "amount_cents": 10000,
            "currency": "EGP",
            "merchant_order_id": "ORD-12345",
            "wallet_notification": null,
            "paid_amount_cents": 10000,
            "notify_user_with_email": false,
            "items": []
        },
        "created_at": "2024-01-15T10:30:00",
        "transaction_processed_callback_responses": [],
        "currency": "EGP",
        "source_data": {
            "type": "card",
            "pan": "1234",
            "sub_type": "Visa"
        },
        "api_source": "MOBILE",
        "is_void": false,
        "is_refund": false,
        "data": {
            "message": "Approved",
            "success": true
        },
        "is_captured": false,
        "is_bill": false,
        "owner": 123,
        "parent_transaction": null
    }
}
```

### Paymob Callback Handling

#### 1. Create Route

In `routes/web.php`:

```php
Route::post('/payment/callback/paymob', [PaymentCallbackController::class, 'paymob'])
    ->name('payments.paymob.callback');
```

#### 2. Handle Callback

```php
use Payments\Facades\Payments;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    public function paymob(Request $request)
    {
        $payload = $request->all();
        
        // Paymob sends transaction data
        $transactionId = $payload['obj']['id'] ?? null;
        
        if (!$transactionId) {
            return response()->json(['message' => 'Invalid callback'], 400);
        }
        
        // Verify payment status with Paymob
        $response = Payments::driver('paymob')->status([
            'auth_token'     => env('PAYMOB_TOKEN'),
            'transaction_id' => $transactionId,
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            
            if ($data['success'] ?? false) {
                $transactionData = $data['obj'] ?? [];
                $isSuccess = $transactionData['success'] ?? false;
                
                // Update your database based on status
                if ($isSuccess) {
                    // Mark payment as paid
                    // Update order status
                    // Send confirmation email
                    // ...
                } else {
                    // Mark payment as failed
                    // Notify customer
                    // ...
                }
            }
        }
        
        // Always return success to Paymob
        return response()->json(['message' => 'ok']);
    }
}
```

---

## Usage Methods

### Method 1: Helper Functions (Simplest - Recommended)

Use helper functions directly - they use the default driver from config:

```php
use Payments\Facades\Payments;

// Uses default driver (from PAYMENT_DRIVER in .env)
$response = Payments::pay([
    'InvoiceValue' => 100,
    'CustomerName' => 'Ahmed',
]);

// Or specify driver
$response = Payments::pay([...], 'myfatoorah');
$response = Payments::refund([...], 'paymob');
$response = Payments::status([...], 'stripe');
```

### Method 2: Driver/Gateway

Get a driver instance and call methods:

```php
// Using driver()
$response = Payments::driver('myfatoorah')->pay([...]);
$response = Payments::driver('paymob')->refund([...]);
$response = Payments::driver('stripe')->status([...]);

// Using gateway() - same as driver()
$response = Payments::gateway('myfatoorah')->pay([...]);
```

### Method 3: Custom Actions from Config

Call any action defined in `config/payments.php`:

```php
// Using action() method
$response = Payments::action('custom_action', [...]);
$response = Payments::driver('paymob')->action('custom_action', [...]);

// Using magic method (shorter syntax)
$response = Payments::driver('paymob')->custom_action([...]);
```

### Method 4: Dependency Injection

```php
use Payments\Payments;

class PaymentController extends Controller
{
    public function __construct(
        protected Payments $payments
    ) {}

    public function process()
    {
        $response = $this->payments
            ->driver('myfatoorah')
            ->pay([
                'InvoiceValue' => 100,
                'CustomerName' => 'Ahmed',
            ]);
        
        if ($response->successful()) {
            // Handle success
        }
    }
}
```

---

## Handling Responses

### Response Methods

All responses are Laravel HTTP Client responses with these methods:

```php
$response->successful();  // bool - HTTP 2xx status codes
$response->failed();      // bool - HTTP 4xx/5xx status codes
$response->status();      // int - HTTP status code
$response->json();        // array - JSON response body
$response->body();        // string - Raw response body
$response->headers();     // array - Response headers
```

### Success / Error Handling

```php
$response = Payments::driver('myfatoorah')->pay([...]);

if ($response->successful()) {
    $data = $response->json();
    
    // Check gateway-specific success indicators
    if ($data['IsSuccess'] ?? false) { // MyFatoorah
        // Handle success
        $paymentUrl = $data['Data']['InvoiceURL'] ?? null;
        return redirect()->away($paymentUrl);
    }
}

if ($response->failed()) {
    // Log error
    \Log::error('Payment failed', [
        'status' => $response->status(),
        'body'   => $response->json(),
    ]);
    
    return back()->with('error', 'Payment failed. Please try again.');
}
```

---

## Advanced Usage

### Adding Custom Actions to Config

You can add any custom action to `config/payments.php`:

```php
'myfatoorah' => [
    // ... existing config ...
    'actions' => [
        'pay' => [...],
        'refund' => [...],
        'status' => [...],
        // Add custom action
        'get_invoice' => [
            'method' => 'GET',
            'path' => '/v2/GetInvoice/{invoice_id}',
            'placeholders' => [
                'invoice_id' => 'invoice_id',
            ],
        ],
        'send_payment' => [
            'method' => 'POST',
            'path' => '/v2/SendPayment',
            'options' => [
                'timeout' => 60,
                'headers' => [
                    'X-Custom-Header' => 'value',
                ],
            ],
        ],
    ],
],
```

### Using Custom Actions

```php
// Custom action with placeholder
$response = Payments::driver('myfatoorah')->action('get_invoice', [], [], [
    'invoice_id' => 123456,
]);

// Or using magic method
$response = Payments::driver('myfatoorah')->get_invoice([
    'invoice_id' => 123456,
]);
```

### Direct HTTP Request

For complete control over the request:

```php
$response = Payments::driver('myfatoorah')->request('POST', '/v2/CustomEndpoint', [
    'json' => [
        'custom_field' => 'value',
    ],
    'headers' => [
        'X-Custom-Header' => 'value',
    ],
]);
```

---

## License

MIT
