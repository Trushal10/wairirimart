<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cross-table foreign keys that couldn't live in their owning table's
 * base migration because the referenced table is created later in the
 * migration timeline. The columns themselves are declared in the base
 * migrations; only the CONSTRAINTS are added here.
 *
 * Idempotent — safe to run against a DB where FKs may have been added
 * by an earlier run and the migrations row is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->foreignKeyExists('orders', 'orders_delivery_partner_id_foreign')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('delivery_partner_id')
                    ->references('id')->on('delivery_partners')
                    ->nullOnDelete();
            });
        }

        if (! $this->foreignKeyExists('order_items', 'order_items_product_variant_id_foreign')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_variant_id')
                    ->references('id')->on('product_variants')
                    ->nullOnDelete();
            });
        }

        if (! $this->foreignKeyExists('product_medias', 'product_medias_product_variant_id_foreign')) {
            Schema::table('product_medias', function (Blueprint $table) {
                $table->foreign('product_variant_id')
                    ->references('id')->on('product_variants')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->foreignKeyExists('product_medias', 'product_medias_product_variant_id_foreign')) {
            Schema::table('product_medias', function (Blueprint $table) {
                $table->dropForeign(['product_variant_id']);
            });
        }

        if ($this->foreignKeyExists('order_items', 'order_items_product_variant_id_foreign')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['product_variant_id']);
            });
        }

        if ($this->foreignKeyExists('orders', 'orders_delivery_partner_id_foreign')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['delivery_partner_id']);
            });
        }
    }

    protected function foreignKeyExists(string $table, string $constraint): bool
    {
        $schema = DB::connection()->getDatabaseName();
        $count = DB::selectOne('
            SELECT COUNT(*) AS n
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_TYPE = ?
              AND TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
        ', ['FOREIGN KEY', $schema, $table, $constraint]);

        return ((int) ($count->n ?? 0)) > 0;
    }
};
