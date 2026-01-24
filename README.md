# Payments Laravel Package

حزمة Laravel موحدة لمعالجة المدفوعات مع بوابات متعددة (MyFatoorah, Paymob, PayPal, Stripe).

## المتطلبات

- PHP >= 8.1
- Laravel >= 10.0

**ملاحظة:** إذا كنت تستخدم Laravel 12، تأكد من أن PHP version >= 8.4

## التثبيت

```bash
composer require mohameda1998/payments
php artisan vendor:publish --tag=payments-config
php artisan vendor:publish --tag=payments-migrations
php artisan migrate
```

**ملاحظة:** إذا واجهت مشكلة في التثبيت بسبب PHP version، تأكد من:
1. أن PHP version يطابق متطلبات Laravel
2. أو احذف `config.platform.php` من `composer.json` إذا كان يسبب مشاكل

## الإعدادات

أضف في ملف `.env`:

```env
PAYMENT_DRIVER=myfatoorah
MYFATOORAH_TOKEN=your_token_here
PAYMENT_WEBHOOK_TOKEN=your_webhook_token_here
```

## الاستخدام السريع

### الخطوة 1: إضافة Trait للموديل

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Payments\Traits\HasPayments;

class Order extends Model
{
    use HasPayments;

    /**
     * إرجاع بيانات المستخدم للدفع
     * هذه البيانات ستُملأ تلقائياً في payload الدفع
     */
    public function getUserPayloadData(): array
    {
        return [
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'user_mobile' => $this->user->phone,
            'reference_id' => $this->order_number ?? 'ORD-' . $this->id,
        ];
    }
}
```

### الخطوة 2: إنشاء دفعة

```php
$order = Order::find(1);

// حدد صفحة واحدة فقط - الباكدج سيرجع عليها سواء نجح الدفع أو فشل
$transaction = $order->makePayment([
    'InvoiceValue' => $order->total,
    'CurrencyIso' => 'KWD',
], 'myfatoorah', [
    'redirect_url' => '/orders/' . $order->id . '/payment-result', // صفحة واحدة فقط
]);

// إعادة توجيه المستخدم لصفحة الدفع
if ($transaction->isSuccessful()) {
    $paymentUrl = $transaction->response_data['Data']['InvoiceURL'] ?? null;
    return redirect()->away($paymentUrl);
}
```

### الخطوة 3: معالجة النتيجة في صفحتك

بعد أن يدفع المستخدم، الباكدج سيعيد توجيهه تلقائياً للصفحة التي حددتها مع query parameters:

```
/orders/123/payment-result?transaction_id=xxx&status=success&reference_id=ORD-123&payable_id=1
```

في Controller الخاص بك:

```php
// app/Http/Controllers/OrderController.php
public function paymentResult(Request $request, $orderId)
{
    $order = Order::findOrFail($orderId);
    $status = $request->query('status'); // 'success' أو 'failed'
    $transactionId = $request->query('transaction_id');
    
    // البحث عن المعاملة
    $transaction = \Payments\Models\PaymentTransaction::where('transaction_id', $transactionId)->first();
    
    if ($status === 'success' && $transaction && $transaction->isSuccessful()) {
        // الدفع نجح
        $order->update(['payment_status' => 'paid']);
        return view('orders.payment-success', compact('order', 'transaction'));
    } else {
        // الدفع فشل
        return view('orders.payment-failed', compact('order', 'transaction'));
    }
}
```

**ملاحظة مهمة:** الباكدج يعيد التوجيه لصفحة واحدة فقط. أنت تتحقق من `status` في query parameters لتعرف إذا نجح الدفع أم فشل.

## الخيارات المتقدمة

### تحديد صفحات منفصلة (اختياري)

إذا أردت صفحات منفصلة للنجاح والفشل:

```php
$transaction = $order->makePayment([
    'InvoiceValue' => $order->total,
    'CurrencyIso' => 'KWD',
], 'myfatoorah', [
    'success_url' => '/orders/' . $order->id . '/success',  // للنجاح
    'error_url' => '/orders/' . $order->id . '/error',      // للفشل
]);
```

### استخدام صفحة واحدة (موصى به)

```php
$transaction = $order->makePayment([
    'InvoiceValue' => $order->total,
    'CurrencyIso' => 'KWD',
], 'myfatoorah', [
    'redirect_url' => '/orders/' . $order->id . '/payment-result', // صفحة واحدة
]);
```

ثم في Controller:

```php
public function paymentResult(Request $request, $orderId)
{
    $status = $request->query('status'); // 'success' أو 'failed'
    
    if ($status === 'success') {
        return view('orders.success');
    } else {
        return view('orders.error');
    }
}
```

## Webhook (إشعارات البوابة)

**مهم:** كل gateway له طريقة خاصة في التعامل مع webhook. يمكنك تخصيص route لكل gateway حسب متطلباته.

### إعداد Webhook Routes

كل gateway يمكن أن يكون له route مخصص. إذا لم تحدد route مخصص، سيستخدم الـ route الافتراضي.

#### الطريقة 1: استخدام Routes الافتراضية

إذا لم تحدد route مخصص، الباكدج يستخدم routes افتراضية:

**MyFatoorah:**
```
https://yourdomain.com/payments/webhook/myfatoorah
```

**Paymob:**
```
https://yourdomain.com/payments/webhook/paymob
```

#### الطريقة 2: تخصيص Route لكل Gateway (موصى به)

إذا كان gateway يطلب route معين باسم معين، يمكنك تخصيصه في `.env`:

```env
# MyFatoorah يطلب route باسم 'myfatoorah-webhook'
MYFATOORAH_WEBHOOK_ROUTE=myfatoorah-webhook

# Paymob يطلب route باسم 'paymob-webhook'  
PAYMOB_WEBHOOK_ROUTE=paymob-webhook

# أو routes كاملة
MYFATOORAH_WEBHOOK_ROUTE=payments/myfatoorah/webhook
PAYMOB_WEBHOOK_ROUTE=payments/paymob/webhook
```

**أمثلة Routes المخصصة:**

**MyFatoorah:**
```
https://yourdomain.com/myfatoorah-webhook
```

**Paymob:**
```
https://yourdomain.com/paymob-webhook
```

**ملاحظة:** يمكنك أيضاً تحديد HTTP methods المقبولة لكل gateway في config.

#### مثال: MyFatoorah يطلب route باسم محدد

**في `.env`:**
```env
MYFATOORAH_WEBHOOK_ROUTE=myfatoorah-webhook
MYFATOORAH_WEBHOOK_TOKEN=my-secret-token
```

**في لوحة تحكم MyFatoorah:**
```
Webhook URL: https://yourdomain.com/myfatoorah-webhook
```

#### مثال: Paymob يطلب route باسم محدد

**في `.env`:**
```env
PAYMOB_WEBHOOK_ROUTE=paymob-webhook
PAYMOB_WEBHOOK_TOKEN=paymob-secret-token
```

**في لوحة تحكم Paymob:**
```
Webhook URL: https://yourdomain.com/paymob-webhook
```

**ملاحظة:** إذا لم تحدد `webhook_route` في config، الباكدج يستخدم الـ route الافتراضي: `/payments/webhook/{driver}`

### إعداد Webhook Tokens

يمكنك استخدام token عام لجميع البوابات أو token خاص لكل gateway:

**طريقة 1: Token عام (لجميع البوابات)**
```env
PAYMENT_WEBHOOK_TOKEN=your-secret-token-here
```

**طريقة 2: Token خاص لكل gateway (موصى به)**
```env
# Token عام (fallback)
PAYMENT_WEBHOOK_TOKEN=general-token

# Token خاص لكل gateway
MYFATOORAH_WEBHOOK_TOKEN=myfatoorah-specific-token
PAYMOB_WEBHOOK_TOKEN=paymob-specific-token
```

**أولوية التحقق من Token:**
1. Token الخاص بالـ gateway (إذا كان موجود)
2. Token العام (fallback)

### كيف يعمل Webhook

1. البوابة ترسل webhook request
2. الباكدج يتحقق من token (خاص بالـ gateway أو العام)
3. الباكدج يستخرج بيانات المعاملة حسب تنسيق كل gateway
4. الباكدج يحدث/ينشئ المعاملة في قاعدة البيانات
5. الباكدج يرجع `200 OK` للبوابة

**ملاحظة:** الباكدج يدعم تلقائياً تنسيقات webhook لكل gateway (MyFatoorah, Paymob, PayPal, Stripe).

## Query Parameters المرسلة مع Redirect

عند إعادة التوجيه، الباكدج يرسل هذه المعاملات:

- `transaction_id` - معرف المعاملة من البوابة
- `reference_id` - معرف المرجع الخاص بك
- `status` - حالة الدفع (`success` أو `failed`)
- `driver` - اسم البوابة
- `payable_type` - نوع الموديل (مثل: `App\Models\Order`)
- `payable_id` - معرف الموديل

## API Methods

```php
// إنشاء دفعة
$transaction = $order->makePayment($payload, $driver, $options);

// استرجاع دفعة
$transaction = $order->refundPayment($payload, $driver, $options);

// العلاقات
$order->paymentTransactions();      // جميع المعاملات
$order->successfulPayments();        // المعاملات الناجحة
$order->failedPayments();           // المعاملات الفاشلة
$order->latestPayment();            // آخر معاملة
$order->hasSuccessfulPayment();     // هل يوجد دفعة ناجحة؟
$order->getTotalPaidAmount();       // إجمالي المدفوع
```

## License

MIT
