<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_partner_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider', 50);              // shiprocket, delhivery, dtdc...
            $table->string('provider_order_id')->nullable();
            $table->string('provider_shipment_id')->nullable();
            $table->string('awb_code')->nullable()->index();
            $table->string('courier_id')->nullable();
            $table->string('courier_name')->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->string('label_url', 500)->nullable();
            $table->string('manifest_url', 500)->nullable();
            $table->string('invoice_url', 500)->nullable();

            $table->string('status', 50)->default('pending')->index();
            $table->string('remark')->nullable();

            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('breadth', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();

            $table->timestamp('pickup_scheduled_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'provider']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 50);
            $table->string('source', 20)->default('system'); // system | admin | webhook | customer
            $table->string('comment', 500)->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('shipments');
    }
};
