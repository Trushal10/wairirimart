<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->json('social_links')->nullable();
            // Editable copy blocks + policy strings shown across the storefront
            // (product detail delivery/returns, top-bar messages, home features).
            $table->json('storefront_content')->nullable();
            $table->string('icon')->nullable();
            $table->string('image', 100)->nullable();
            $table->mediumText('address')->nullable();
            $table->string('city', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
