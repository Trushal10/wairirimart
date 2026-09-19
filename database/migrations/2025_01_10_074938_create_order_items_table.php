<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            // FK to product_variants added in the cross-table FK migration
            // because product_variants is created later in the timeline.
            $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            $table->unsignedInteger('quantity');
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            // Snapshot of the variant's attribute values at time of purchase.
            // e.g. {"Size": "M", "Color": "Red"}
            $table->json('variant_options')->nullable();
            $table->string('product_name_snapshot', 255)->nullable();
            $table->string('variant_sku_snapshot', 100)->nullable();
            $table->decimal('price', 12, 2);
            $table->json('customization')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
