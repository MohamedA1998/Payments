<?php

namespace Payments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Payments\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, ?string $driver = null)
    {
        // استخراج driver من route parameter أو defaults
        if (!$driver) {
            $driver = $request->route('driver');
        }
        if (!$driver && $request->route()) {
            $driver = $request->route()->parameter('driver') ?? $request->route()->defaults['driver'] ?? null;
        }
        
        if (!$driver) {
            Log::error('Webhook driver not specified');
            return response('Bad Request', 400);
        }
        
        try {
            if (!$this->verifyToken($request, $driver)) {
                Log::warning('Webhook token verification failed', ['driver' => $driver]);
                return response('Unauthorized', 401);
            }

            $transactionData = $this->extractTransactionData($request, $driver);
            $transaction = $this->findOrCreateTransaction($request, $driver, $transactionData);
            $this->updateTransaction($transaction, $request, $driver, $transactionData);

            Log::info('Webhook processed', ['driver' => $driver, 'transaction_id' => $transaction->transaction_id]);
            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Webhook error', ['driver' => $driver, 'error' => $e->getMessage()]);
            return response('Internal Server Error', 500);
        }
    }

    protected function verifyToken(Request $request, string $driver): bool
    {
        // أولاً: محاولة استخدام token خاص بالـ gateway
        $driverConfig = config("payments.drivers.{$driver}", []);
        $token = $driverConfig['webhook_token'] ?? null;
        
        // ثانياً: استخدام token العام
        if (!$token) {
            $token = config('payments.webhook.token');
        }
        
        // إذا لم يكن هناك token، السماح (للتطوير فقط)
        if (!$token) {
            Log::warning('Webhook token not configured', ['driver' => $driver]);
            return true;
        }
        
        // استخراج token من الطلب
        $requestToken = $request->header('X-Webhook-Token')
            ?? $request->header('Authorization')
            ?? $request->input('token')
            ?? $request->input('webhook_token');
            
        if ($requestToken && str_starts_with($requestToken, 'Bearer ')) {
            $requestToken = substr($requestToken, 7);
        }
        
        return $requestToken && $requestToken === $token;
    }

    protected function extractTransactionData(Request $request, string $driver): array
    {
        $data = $request->all();
        
        return match($driver) {
            'myfatoorah' => [
                'transaction_id' => $data['Data']['InvoiceId'] ?? $data['InvoiceId'] ?? $data['paymentId'] ?? null,
                'reference_id' => $data['Data']['CustomerReference'] ?? $data['CustomerReference'] ?? null,
                'status' => $this->mapStatus($data['Data']['InvoiceStatus'] ?? $data['InvoiceStatus'] ?? 'pending', $driver),
                'amount' => $data['Data']['InvoiceValue'] ?? $data['InvoiceValue'] ?? null,
                'currency' => $data['Data']['Currency'] ?? $data['Currency'] ?? null,
            ],
            'paymob' => [
                'transaction_id' => $data['obj']['id'] ?? $data['id'] ?? $data['transaction_id'] ?? null,
                'reference_id' => $data['obj']['order']['merchant_order_id'] ?? $data['order_id'] ?? null,
                'status' => $this->mapStatus($data['obj']['success'] ?? $data['success'] ?? false, $driver),
                'amount' => isset($data['obj']['amount_cents']) ? $data['obj']['amount_cents'] / 100 : ($data['amount'] ?? null),
                'currency' => $data['obj']['currency'] ?? $data['currency'] ?? null,
            ],
            'paypal' => [
                'transaction_id' => $data['resource']['id'] ?? $data['id'] ?? null,
                'reference_id' => $data['resource']['invoice_id'] ?? $data['invoice_id'] ?? null,
                'status' => $this->mapStatus($data['resource']['status'] ?? $data['status'] ?? 'pending', $driver),
                'amount' => $data['resource']['amount']['value'] ?? $data['amount'] ?? null,
                'currency' => $data['resource']['amount']['currency_code'] ?? $data['currency'] ?? null,
            ],
            'stripe' => [
                'transaction_id' => $data['data']['object']['id'] ?? $data['id'] ?? null,
                'reference_id' => $data['data']['object']['metadata']['reference_id'] ?? $data['reference_id'] ?? null,
                'status' => $this->mapStatus($data['data']['object']['status'] ?? $data['type'] ?? 'pending', $driver),
                'amount' => isset($data['data']['object']['amount']) ? $data['data']['object']['amount'] / 100 : ($data['amount'] ?? null),
                'currency' => $data['data']['object']['currency'] ?? $data['currency'] ?? null,
            ],
            default => [
                'transaction_id' => $data['transaction_id'] ?? $data['id'] ?? null,
                'reference_id' => $data['reference_id'] ?? $data['order_id'] ?? null,
                'status' => $this->mapStatus($data['status'] ?? 'pending', $driver),
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? null,
            ],
        };
    }

    protected function findOrCreateTransaction(Request $request, string $driver, array $transactionData): PaymentTransaction
    {
        if ($transactionData['transaction_id']) {
            $transaction = PaymentTransaction::where('driver', $driver)
                ->where(function ($q) use ($transactionData) {
                    $q->where('transaction_id', $transactionData['transaction_id'])
                      ->orWhere('reference_id', $transactionData['transaction_id']);
                })->latest()->first();
            if ($transaction) return $transaction;
        }
        return PaymentTransaction::create([
            'driver' => $driver,
            'action' => 'pay',
            'status' => $transactionData['status'] ?? 'pending',
            'transaction_id' => $transactionData['transaction_id'] ?? null,
            'reference_id' => $transactionData['reference_id'] ?? null,
            'amount' => $transactionData['amount'] ?? null,
            'currency' => $transactionData['currency'] ?? null,
            'request_payload' => $request->all(),
            'response_data' => $request->all(),
            'is_successful' => ($transactionData['status'] ?? 'pending') === 'success',
        ]);
    }

    protected function updateTransaction(PaymentTransaction $transaction, Request $request, string $driver, array $transactionData): void
    {
        $transaction->update([
            'response_data' => array_merge($transaction->response_data ?? [], $request->all()),
            'status' => $transactionData['status'] ?? $transaction->status,
            'is_successful' => ($transactionData['status'] ?? $transaction->status) === 'success',
            'transaction_id' => $transactionData['transaction_id'] ?: $transaction->transaction_id,
            'amount' => $transactionData['amount'] ?: $transaction->amount,
            'currency' => $transactionData['currency'] ?: $transaction->currency,
        ]);
    }

    protected function mapStatus($status, string $driver): string
    {
        return match($driver) {
            'myfatoorah' => in_array($status, ['Paid', 'paid', 'Success']) ? 'success' : 'failed',
            'paymob' => ($status === true || $status === 'true' || $status === 1) ? 'success' : 'failed',
            'paypal' => in_array($status, ['COMPLETED', 'completed', 'APPROVED']) ? 'success' : 'failed',
            'stripe' => in_array($status, ['succeeded', 'paid', 'payment_intent.succeeded']) ? 'success' : 'failed',
            default => ($status === 'success' || $status === true || $status === 'true') ? 'success' : 'failed',
        };
    }
}
