<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Period-based operating expenses (rent, salaries, marketing spend, etc.)
 * consumed by the P&L report. Distinct from `orders.other_expense` which
 * is per-order incidental cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->date('date')->index();
            $t->string('category', 60)->index();       // e.g. rent, salary, marketing, utility, other
            $t->string('title', 200);
            $t->text('note')->nullable();
            $t->decimal('amount', 12, 2);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();

            $t->index(['date', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
