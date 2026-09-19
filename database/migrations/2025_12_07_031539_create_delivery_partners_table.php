<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);            // Sherofex, Shiprocket, DTDC...
            $table->string('description', 500)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('code', 50)->unique();   // 'sherofex', 'shiprocket', ...
            $table->boolean('is_third_party')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->enum('mode', ['test', 'live'])->default('test');

            $table->string('api_base_url')->nullable();
            $table->string('api_key')->nullable();
            $table->text('credentials')->nullable();     // Laravel-encrypted JSON blob
            $table->json('config')->nullable();          // non-sensitive settings
            $table->json('supports')->nullable();        // ['refund','webhook','partial_refund']
            $table->unsignedTinyInteger('priority')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_partners');
    }
};
