<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->unsignedTinyInteger('rate');
            $table->string('title', 255);
            $table->text('review');
            $table->string('name', 100);
            $table->string('email');
            $table->string('image')->nullable();
            $table->boolean('is_approved')->default(false)->index();
            $table->boolean('is_spam')->default(false);

            $table->timestamps();

            $table->index(['product_id', 'is_approved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
