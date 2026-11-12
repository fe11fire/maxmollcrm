<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check all data before MySQL DDL (which is not transactionally rolled back).
        foreach (['stocks' => ['product_id', 'warehouse_id'], 'order_items' => ['order_id', 'product_id']] as $table => $columns) {
            if (DB::table($table)->select($columns)->groupBy(...$columns)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException("Resolve duplicate rows in {$table} before migrating.");
            }
        }
        if (DB::table('stocks')->where('stock', '<', 0)->exists()) {
            throw new RuntimeException('Resolve negative stocks before migrating.');
        }

        Schema::table('stocks', fn (Blueprint $table) => $table->unique(['product_id', 'warehouse_id']));
        Schema::table('order_items', fn (Blueprint $table) => $table->unique(['order_id', 'product_id']));
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_nonnegative CHECK (stock >= 0)');
    }

    public function down(): void
    {
        // MySQL may replace the original FK index with the new composite unique index.
        // Restore a standalone FK index before removing that unique index.
        if (! Schema::hasIndex('order_items', 'order_items_order_id_foreign')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->index('order_id', 'order_items_order_id_foreign'));
        }
        if (! Schema::hasIndex('stocks', 'stocks_product_id_foreign')) {
            Schema::table('stocks', fn (Blueprint $table) => $table->index('product_id', 'stocks_product_id_foreign'));
        }
        DB::statement('ALTER TABLE stocks DROP CHECK stocks_nonnegative');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropUnique(['order_id', 'product_id']));
        Schema::table('stocks', fn (Blueprint $table) => $table->dropUnique(['product_id', 'warehouse_id']));
    }
};
