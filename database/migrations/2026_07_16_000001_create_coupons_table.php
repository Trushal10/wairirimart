<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table may already exist (partial migration from a previous failed attempt)
        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->enum('type', ['fixed', 'percent']);
                $table->decimal('value', 10, 2);
                $table->decimal('min_order_amount', 10, 2)->default(0);
                $table->decimal('max_discount_amount', 10, 2)->nullable()->comment('Cap for percent type');
                $table->unsignedInteger('usage_limit')->nullable()->comment('Null = unlimited');
                $table->unsignedInteger('used_count')->default(0);
                $table->unsignedBigInteger('customer_id')->nullable()->comment('If set, coupon is single-customer only');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['code', 'is_active']);
                $table->index('expires_at');
            });
        }

        // Add FK separately — safe to run even if table already existed without it
        if (Schema::hasTable('customers') && Schema::hasColumn('coupons', 'customer_id')) {
            try {
                Schema::table('coupons', function (Blueprint $table) {
                    $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK may already exist — ignore
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
