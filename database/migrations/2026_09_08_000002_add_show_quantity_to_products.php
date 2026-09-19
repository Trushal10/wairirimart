<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product control over the storefront quantity stepper.
 *
 * Default true, which is what every existing product was already doing, so the
 * column changes nothing until an admin turns it off. Turning it off suits a
 * made-to-order or one-per-customer item, where offering a quantity at all is
 * the wrong question to ask the shopper.
 *
 * Guarded so it is safe to re-run — this install has a history of migrations
 * whose records were lost, which then block everything queued behind them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'show_quantity')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('show_quantity')->default(true)->after('has_variants');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'show_quantity')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('show_quantity');
        });
    }
};
