<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('code', 64)->unique();
            $table->enum('swatch_type', ['none', 'color', 'image'])->default('none');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attribute_id');
            $table->string('value', 128);
            $table->string('label', 128)->nullable();
            $table->string('swatch_value', 64)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->onDelete('cascade');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->decimal('price', 12, 2)->nullable()->comment('null = use products.price');
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('image_url', 255)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('status')->default(true);
            $table->boolean('is_default')->default(false);
            // Dict of { "Variety": "1 kg", "Color": "Red" } — mirrors products.option_types keys.
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')
                ->references('id')->on('products')
                ->onDelete('cascade');
            $table->index(['product_id', 'is_default']);
            $table->index('sku');
            $table->index('barcode');
        });

        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('attribute_id');
            $table->unsignedBigInteger('attribute_value_id');
            $table->timestamps();

            $table->unique(
                ['product_variant_id', 'attribute_id'],
                'pvv_variant_attribute_unique'
            );
            $table->index('attribute_value_id');
            $table->foreign('product_variant_id')
                ->references('id')->on('product_variants')
                ->onDelete('cascade');
            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->onDelete('restrict');
            $table->foreign('attribute_value_id')
                ->references('id')->on('attribute_values')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
