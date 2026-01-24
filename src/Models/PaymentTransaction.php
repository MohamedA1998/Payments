<?php

namespace Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    protected $fillable = [
        'payable_type', 'payable_id', 'user_type', 'user_id',
        'driver', 'action', 'status', 'transaction_id', 'reference_id',
        'amount', 'currency', 'request_payload', 'response_data',
        'user_payload_data', 'metadata', 'http_status_code',
        'is_successful', 'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_data' => 'array',
        'user_payload_data' => 'array',
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'is_successful' => 'boolean',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSuccessful($query)
    {
        return $query->where('is_successful', true)->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('is_successful', false)->orWhere('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByDriver($query, string $driver)
    {
        return $query->where('driver', $driver);
    }

    public function isSuccessful(): bool
    {
        return $this->is_successful && $this->status === 'success';
    }
}
