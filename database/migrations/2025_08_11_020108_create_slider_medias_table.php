<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('slider_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('slider_id');
            $table->string('caption')->nullable();
            $table->string('action_url')->nullable();
            $table->string('url')->nullable();
            $table->enum('type', ['image', 'video']);
            $table->unsignedInteger('priority')->default(0)->nullable();
            $table->foreign('slider_id')->references('id')->on('sliders')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slider_media');
    }
};
