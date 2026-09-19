<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records the moment an order's stock was actually taken out of inventory.
 *
 * The two checkout paths commit stock at different times: COD decrements at
 * order-placement, prepaid only once the payment is captured. Both orders sit
 * at status "pending" in between, so "pending" on its own tells you nothing
 * about whether inventory has moved — and cancelling has to know, or it either
 * loses stock it never took or hands back stock twice.
 *
 * Storing it on the order makes that explicit instead of inferred, and gives
 * the decrement/restore pair an idempotency guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded because migration state drifts: a database can already carry
        // this column from an out-of-band change, and a hard ALTER would abort
        // the whole deploy on "duplicate column". The backfill below still
        // runs, and only fills rows that have no stamp yet.
        if (! Schema::hasColumn('orders', 'stock_committed_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('stock_committed_at')->nullable()->after('status');
            });
        }

        // Backfill existing orders. Anything past pending has had its stock
        // taken (confirmed/delivered both run through the same decrement), and
        // so has any pending COD order, since COD commits at placement time.
        // Pending prepaid orders are left null: their stock is still on the
        // shelf until the payment lands.
        DB::table('orders')
            ->whereNull('stock_committed_at')
            ->whereIn('status', ['confirmed', 'delivered'])
            ->update(['stock_committed_at' => DB::raw('COALESCE(updated_at, created_at)')]);

        DB::table('orders')
            ->whereNull('orders.stock_committed_at')
            ->where('orders.status', 'pending')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('payments')
                    ->whereColumn('payments.order_id', 'orders.id')
                    ->where('payments.type', 'cod');
            })
            ->update(['stock_committed_at' => DB::raw('COALESCE(orders.updated_at, orders.created_at)')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'stock_committed_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('stock_committed_at');
            });
        }
    }
};
