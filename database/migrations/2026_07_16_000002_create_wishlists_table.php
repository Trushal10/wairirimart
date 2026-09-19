<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The table exists on installs where it was created outside the
        // migration record, and an unguarded create() there fails and blocks
        // every later migration in the queue.
        if (Schema::hasTable('wishlists')) {
            return;
        }

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'product_id', 'variant_id']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
