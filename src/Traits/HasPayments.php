<?php

namespace Payments\Traits;

use Payments\Facades\Payments;
use Payments\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

trait HasPayments
{
    public function paymentTransactions(): MorphMany
    {
        return $this->morphMany(PaymentTransaction::class, 'payable');
    }

    public function successfulPayments(): MorphMany
    {
        return $this->paymentTransactions()->successful();
    }

    public function failedPayments(): MorphMany
    {
        return $this->paymentTransactions()->failed();
    }

    public function latestPayment(): ?PaymentTransaction
    {
        return $this->paymentTransactions()->latest()->first();
    }

    public function hasSuccessfulPayment(): bool
    {
        return $this->paymentTransactions()->successful()->exists();
    }

    public function getTotalPaidAmount(): float
    {
        return (float) $this->paymentTransactions()->successful()->where('action', 'pay')->sum('amount');
    }

    public function getUserPayloadData(): array
    {
        if (method_exists($this, 'user') && $this->user) {
            return [
                'user_id' => $this->user->id,
                'user_name' => $this->user->name ?? null,
                'user_email' => $this->user->email ?? null,
                'user_mobile' => $this->user->mobile ?? $this->user->phone ?? null,
            ];
        }
        return [];
    }

    public function makePayment(array $payload, ?string $driver = null, array $options = []): PaymentTransaction
    {
        return DB::transaction(function () use ($payload, $driver, $options) {
            $driver = $driver ?? config('payments.default', 'myfatoorah');
            $userInfo = $this->extractUserInfo($options);
            $userPayloadData = $this->getUserPayloadData();
            $gatewayPayload = $this->mapUserDataToGateway($userPayloadData, $driver, $payload);
            $finalPayload = $this->mergePayloads($gatewayPayload, $payload);

            $metadata = $options['metadata'] ?? [];
            
            // دعم redirect_url (صفحة واحدة) أو success_url/error_url (صفحات منفصلة)
            if (isset($options['redirect_url'])) {
                $metadata['success_url'] = $options['redirect_url'];
                $metadata['error_url'] = $options['redirect_url'];
                $metadata['cancel_url'] = $options['redirect_url'];
            } else {
                $metadata['success_url'] = $options['success_url'] ?? route('payments.callback.success', ['driver' => $driver]);
                $metadata['error_url'] = $options['error_url'] ?? route('payments.callback.error', ['driver' => $driver]);
                $metadata['cancel_url'] = $options['cancel_url'] ?? route('payments.callback.cancel', ['driver' => $driver]);
            }

            if (!isset($finalPayload['CallbackUrl'])) {
                $finalPayload['CallbackUrl'] = $metadata['success_url'];
            }
            if (!isset($finalPayload['ErrorUrl'])) {
                $finalPayload['ErrorUrl'] = $metadata['error_url'];
            }

            $transaction = $this->paymentTransactions()->create([
                'user_type' => $userInfo['user_type'],
                'user_id' => $userInfo['user_id'],
                'driver' => $driver,
                'action' => 'pay',
                'status' => 'pending',
                'reference_id' => $options['reference_id'] ?? $this->extractFromPayload($finalPayload, ['reference_id', 'CustomerReference']),
                'amount' => $options['amount'] ?? $this->extractFromPayload($finalPayload, ['amount', 'InvoiceValue']),
                'currency' => $options['currency'] ?? $this->extractFromPayload($finalPayload, ['currency', 'CurrencyIso']),
                'request_payload' => $finalPayload,
                'user_payload_data' => $userPayloadData,
                'metadata' => $metadata,
            ]);

            try {
                $response = Payments::driver($driver)->pay($finalPayload);
                $transaction->update([
                    'http_status_code' => $response->status(),
                    'is_successful' => $response->successful(),
                    'response_data' => $response->json(),
                    'status' => $response->successful() ? 'success' : 'failed',
                    'transaction_id' => $this->extractTransactionId($response->json(), $driver),
                    'error_message' => $response->successful() ? null : ($response->json()['Message'] ?? $response->body()),
                ]);
                return $transaction->fresh();
            } catch (\Exception $e) {
                $transaction->update([
                    'status' => 'failed',
                    'is_successful' => false,
                    'error_message' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    public function refundPayment(array $payload, ?string $driver = null, array $options = []): PaymentTransaction
    {
        return DB::transaction(function () use ($payload, $driver, $options) {
            $driver = $driver ?? config('payments.default', 'myfatoorah');
            $userInfo = $this->extractUserInfo($options);
            $userPayloadData = $this->getUserPayloadData();

            $transaction = $this->paymentTransactions()->create([
                'user_type' => $userInfo['user_type'],
                'user_id' => $userInfo['user_id'],
                'driver' => $driver,
                'action' => 'refund',
                'status' => 'pending',
                'reference_id' => $options['reference_id'] ?? $this->extractFromPayload($payload, ['reference_id', 'Key']),
                'amount' => $options['amount'] ?? $this->extractFromPayload($payload, ['amount', 'RefundValue']),
                'currency' => $options['currency'] ?? $this->extractFromPayload($payload, ['currency']),
                'request_payload' => $payload,
                'user_payload_data' => $userPayloadData,
                'metadata' => $options['metadata'] ?? [],
            ]);

            try {
                $response = Payments::driver($driver)->refund($payload);
                $transaction->update([
                    'http_status_code' => $response->status(),
                    'is_successful' => $response->successful(),
                    'response_data' => $response->json(),
                    'status' => $response->successful() ? 'refunded' : 'failed',
                    'transaction_id' => $this->extractTransactionId($response->json(), $driver, 'refund'),
                    'error_message' => $response->successful() ? null : ($response->json()['Message'] ?? $response->body()),
                ]);
                return $transaction->fresh();
            } catch (\Exception $e) {
                $transaction->update([
                    'status' => 'failed',
                    'is_successful' => false,
                    'error_message' => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    protected function extractUserInfo(array $options): array
    {
        if (isset($options['user']) && is_object($options['user'])) {
            return ['user_type' => get_class($options['user']), 'user_id' => $options['user']->id];
        }
        if (isset($options['user_type']) && isset($options['user_id'])) {
            return ['user_type' => $options['user_type'], 'user_id' => $options['user_id']];
        }
        return ['user_type' => null, 'user_id' => null];
    }

    protected function extractFromPayload(array $payload, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (isset($payload[$key])) {
                return $payload[$key];
            }
        }
        return $default;
    }

    protected function mergePayloads(array $userPayload, array $providedPayload): array
    {
        $merged = $userPayload;
        foreach ($providedPayload as $key => $value) {
            if (isset($merged[$key]) && is_array($merged[$key]) && is_array($value)) {
                $merged[$key] = $this->mergePayloads($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    protected function mapUserDataToGateway(array $userData, string $driver, array $existingPayload = []): array
    {
        $mapped = [];
        if ($driver === 'myfatoorah') {
            if (!isset($existingPayload['CustomerName']) && isset($userData['user_name'])) {
                $mapped['CustomerName'] = $userData['user_name'];
            }
            if (!isset($existingPayload['CustomerEmail']) && isset($userData['user_email'])) {
                $mapped['CustomerEmail'] = $userData['user_email'];
            }
            if (!isset($existingPayload['CustomerMobile']) && isset($userData['user_mobile'])) {
                $mapped['CustomerMobile'] = $userData['user_mobile'];
            }
            if (!isset($existingPayload['CustomerReference']) && isset($userData['reference_id'])) {
                $mapped['CustomerReference'] = $userData['reference_id'];
            } elseif (!isset($existingPayload['CustomerReference']) && isset($this->id)) {
                $mapped['CustomerReference'] = get_class($this) . '-' . $this->id;
            }
        }
        return $mapped;
    }

    protected function extractTransactionId(?array $responseData, string $driver, string $action = 'pay'): ?string
    {
        if (!$responseData) return null;
        if ($driver === 'myfatoorah') {
            return $responseData['Data']['InvoiceId'] ?? $responseData['Data']['InvoiceURL'] ?? null;
        }
        return $responseData['id'] ?? $responseData['transaction_id'] ?? null;
    }
}
