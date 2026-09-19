<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 40)->unique(); // customer-facing e.g. RET-20260714-0001
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Lifecycle: requested → approved | rejected
            //            approved → received → refunded | closed
            //            cancelled from any pre-refunded state
            $table->string('status', 30)->default('requested')->index();
            $table->string('reason', 40); // damaged | wrong_item | not_as_described | size_issue | other
            $table->string('comment', 500)->nullable();
            $table->string('photo', 255)->nullable();

            $table->string('rejection_reason', 500)->nullable();
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->boolean('restock_on_receipt')->default(true);

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            // Snapshot of unit price at return time (products may re-price later).
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();

            $table->unique(['return_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
