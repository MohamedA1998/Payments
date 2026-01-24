<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->nullableMorphs('user');
            $table->string('driver')->index();
            $table->string('action')->index();
            $table->string('status')->default('pending')->index();
            $table->string('transaction_id')->nullable()->index();
            $table->string('reference_id')->nullable()->index();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_data')->nullable();
            $table->json('user_payload_data')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('http_status_code')->nullable();
            $table->boolean('is_successful')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['user_type', 'user_id']);
            $table->index(['driver', 'action', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
