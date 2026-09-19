<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed banner for the inner pages.
 *
 * Every page that is not the home page renders the same hero strip through
 * client/partials/page-hero, and its artwork was a file baked into the theme —
 * changing it meant editing a Blade template and redeploying. One upload here
 * now feeds all of them.
 *
 * Nullable with no default, so an install that has not uploaded one keeps
 * showing the bundled artwork. Guarded, so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('settings', 'page_hero_image')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->string('page_hero_image', 100)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('settings', 'page_hero_image')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('page_hero_image');
        });
    }
};
