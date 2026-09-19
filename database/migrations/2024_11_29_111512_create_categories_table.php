<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('description', 500)->nullable();
            $table->string('image')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1 => Active, 0 => Inactive');
            $table->tinyInteger('featured')->default(1)->comment('1 => show, 0 => not show');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
