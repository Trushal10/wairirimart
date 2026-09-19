<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_medias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            // FK to product_variants added in the cross-table FK migration
            // because product_variants is created later in the migration timeline.
            $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            $table->string('url');
            $table->string('alt_text', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->enum('type', ['image', 'video'])->default('image');
            $table->unsignedInteger('priority')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_medias');
    }
};
