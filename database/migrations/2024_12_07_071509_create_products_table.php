<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('short_description');
            $table->text('description')->nullable();
            $table->text('return_policy')->nullable();

            // SEO / social meta
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('og_image', 255)->nullable();

            // Merch attributes
            $table->string('brand', 128)->nullable();
            $table->string('tax_class', 64)->nullable();
            $table->string('hs_code', 32)->nullable();

            // Legacy option fields (kept for backward compatibility with pre-variant data)
            $table->json('sizes')->nullable();
            $table->json('color')->nullable();

            // Pricing + inventory
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compere_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('barcode', 100)->nullable();

            $table->tinyInteger('status')->default(1)->comment('1 => Active, 0 => Inactive');
            $table->tinyInteger('featured')->default(1)->comment('1 => show, 0 => not show');
            $table->boolean('has_variants')->default(false)->index();

            // Per-product option group definitions: [{ "name": "Variety", "type": "text" }]
            $table->json('option_types')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
