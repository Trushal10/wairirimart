<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            // Email nullable so phone-only signups work; unique when present
            // (MySQL's UNIQUE index natively allows multiple NULLs).
            $table->string('email', 100)->nullable()->unique();
            $table->string('google_id', 100)->nullable()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('image')->nullable();
            $table->string('avatar', 500)->nullable();
            $table->string('provider', 30)->nullable();
            // Non-null blocked_at means admin has blocked this customer.
            $table->timestamp('blocked_at')->nullable()->index();
            $table->string('block_reason', 255)->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Unique phone with NULLs allowed (MySQL native behaviour).
            $table->unique('phone', 'customers_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
