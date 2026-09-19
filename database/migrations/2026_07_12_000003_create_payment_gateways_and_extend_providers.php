<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();       // razorpay, stripe, paypal, cod
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('logo', 255)->nullable();
            $table->enum('mode', ['test', 'live'])->default('test');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->text('credentials')->nullable();     // Laravel-encrypted JSON blob
            $table->json('config')->nullable();          // non-sensitive settings
            $table->json('supports')->nullable();        // ['refund','webhook','partial_refund']
            $table->unsignedTinyInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);            // gateway.update, courier.toggle, ...
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('changes')->nullable();      // before/after
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
        Schema::dropIfExists('payment_gateways');
    }
};
