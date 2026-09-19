<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Until now, switching a product's "Enable variants" toggle off left its
 * product_variants rows in place: the product fell back to its own stock/price
 * columns while the stock rollups (admin listing, product cards) kept summing
 * the abandoned variants, and the stale variant ids stayed addressable from the
 * cart. The save path now removes them; this clears the rows that leaked
 * through before the fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_variants')
            ->whereNull('deleted_at')
            ->whereIn('product_id', function ($q) {
                $q->select('id')->from('products')->where('has_variants', 0);
            })
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible by design: we cannot tell these apart from variants that
        // were deleted deliberately in the admin.
    }
};
