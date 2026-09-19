<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            // FK to delivery_partners added in the cross-table FK migration
            // because delivery_partners is created later in the timeline.
            $table->unsignedBigInteger('delivery_partner_id')->nullable();
            $table->string('order_no')->unique();
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('shipping', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('other_expense', 12, 2)->default(0);
            $table->string('coupan_code', 20)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['pending', 'delivered', 'confirmed', 'canceled'])->default('pending')->index();
            $table->string('shipping_name', 50);
            $table->string('shipping_email', 100);
            $table->string('shipping_phone', 50);
            $table->string('shipping_city', 50);
            $table->string('shipping_pincode', 20);
            $table->string('shipping_state', 50);
            $table->string('shipping_address', 255);

            // Shipping provider / courier metadata (populated when a shipment is booked)
            $table->string('shipping_provider', 50)->nullable();
            $table->string('shipping_service_code', 50)->nullable();
            $table->string('shipping_service_name', 100)->nullable();
            $table->string('shipping_eta', 50)->nullable();
            $table->string('shipping_tracking_number', 100)->nullable();
            $table->string('shipping_label_url', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
