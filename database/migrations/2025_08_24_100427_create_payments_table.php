<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('type'); // cod | razorpay | stripe | ...
            $table->string('payment_id')->nullable();     // provider payment reference
            $table->string('refund_id')->nullable();      // provider refund reference
            $table->string('status')->default('pending'); // pending | paid | failed | refunded
            $table->decimal('amount', 12, 2);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->decimal('gateway_fee', 12, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
