<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Videos for the home page reel.
 *
 * Each row is one uploaded clip. The storefront plays the active ones in
 * priority order, one after another, and scrolls the reel along as each ends.
 * Files live in storage/app/public/home-video/; only the filename is stored,
 * the same convention sliders use.
 *
 * Guarded so it is safe to re-run — this install has a history of migrations
 * whose records were lost, which then block everything queued behind them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_videos')) {
            return;
        }

        Schema::create('home_videos', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80)->nullable();
            $table->string('subtitle', 160)->nullable();
            $table->string('video');
            $table->string('poster')->nullable();
            $table->string('link_url', 255)->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_videos');
    }
};
