<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blogs')) {
            Schema::create('blogs', function (Blueprint $table) {
                $table->id();
                $table->string('title', 255);
                $table->string('slug', 255)->unique();
                $table->string('category', 100)->nullable();
                $table->string('author', 100)->nullable();
                $table->text('excerpt')->nullable();
                $table->longText('content');
                $table->string('image', 255)->nullable();
                $table->string('meta_title', 255)->nullable();
                $table->string('meta_description', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'published_at']);
                $table->index('category');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
