<?php

namespace Payments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Payments\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function handle(Request $request, string $driver, string $status = 'success')
    {
        try {
            $transactionId = $this->extractTransactionId($request, $driver);
            $transaction = $this->findOrCreateTransaction($request, $driver, $transactionId, $status);
            $this->updateTransaction($transaction, $request, $driver, $status);
            $redirectUrl = $this->getRedirectUrl($status, $transaction);
            return redirect($redirectUrl . '?' . http_build_query([
                'transaction_id' => $transaction->transaction_id,
                'reference_id' => $transaction->reference_id,
                'status' => $transaction->status,
                'driver' => $transaction->driver,
                'payable_type' => $transaction->payable_type,
                'payable_id' => $transaction->payable_id,
            ]));
        } catch (\Exception $e) {
            Log::error('Payment callback error', ['driver' => $driver, 'error' => $e->getMessage()]);
            return redirect(config("payments.callback.error_url", '/payment/error'));
        }
    }

    protected function extractTransactionId(Request $request, string $driver): ?string
    {
        return match($driver) {
            'myfatoorah' => $request->input('paymentId') ?? $request->input('InvoiceId'),
            'paymob' => $request->input('id') ?? $request->input('transaction_id'),
            default => $request->input('transaction_id') ?? $request->input('id'),
        };
    }

    protected function findOrCreateTransaction(Request $request, string $driver, ?string $transactionId, string $status): PaymentTransaction
    {
        if ($transactionId) {
            $transaction = PaymentTransaction::where('driver', $driver)
                ->where(function ($q) use ($transactionId) {
                    $q->where('transaction_id', $transactionId)->orWhere('reference_id', $transactionId);
                })->latest()->first();
            if ($transaction) return $transaction;
        }
        return PaymentTransaction::create([
            'driver' => $driver,
            'action' => 'pay',
            'status' => $status === 'success' ? 'success' : 'failed',
            'transaction_id' => $transactionId,
            'request_payload' => $request->all(),
            'response_data' => $request->all(),
            'is_successful' => $status === 'success',
        ]);
    }

    protected function updateTransaction(PaymentTransaction $transaction, Request $request, string $driver, string $status): void
    {
        $transaction->update([
            'response_data' => array_merge($transaction->response_data ?? [], $request->all()),
            'status' => $status === 'success' ? 'success' : 'failed',
            'is_successful' => $status === 'success',
            'transaction_id' => $transaction->transaction_id ?: $this->extractTransactionId($request, $driver),
        ]);
    }

    protected function getRedirectUrl(string $status, ?PaymentTransaction $transaction = null): string
    {
        if ($transaction && $transaction->metadata) {
            $key = match($status) {
                'success' => 'success_url',
                'error' => 'error_url',
                'cancel' => 'cancel_url',
                default => 'error_url',
            };
            if (isset($transaction->metadata[$key])) {
                $url = $transaction->metadata[$key];
                if ($transaction->payable_id) {
                    $url = str_replace(['{id}', '{payable_id}'], $transaction->payable_id, $url);
                }
                return $url;
            }
        }
        $configKey = match($status) {
            'success' => 'callback.success_url',
            'error' => 'callback.error_url',
            'cancel' => 'callback.cancel_url',
            default => 'callback.error_url',
        };
        return config("payments.{$configKey}", '/');
    }
}
