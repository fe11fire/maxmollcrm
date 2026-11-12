<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    private int $product;

    private int $warehouse;

    private int $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = DB::table('products')->insertGetId(['name' => 'Test product', 'price' => 10]);
        $this->warehouse = DB::table('warehouses')->insertGetId(['name' => 'Order warehouse']);
        $this->order = DB::table('orders')->insertGetId(['customer' => 'Test customer', 'warehouse_id' => $this->warehouse]);
    }

    private function stock(int $warehouse, int $count): void
    {
        DB::table('stocks')->insert(['product_id' => $this->product, 'warehouse_id' => $warehouse, 'stock' => $count]);
    }

    private function item(): OrderItem
    {
        return OrderItem::create(['product_id' => $this->product, 'order_id' => $this->order, 'count' => 5]);
    }

    public function test_deduction_across_warehouses_preserves_total_and_history(): void
    {
        $other = DB::table('warehouses')->insertGetId(['name' => 'Other warehouse']);
        $this->stock($this->warehouse, 3);
        $this->stock($other, 5);

        Stock::subStocks($this->product, 6, $this->order);

        $this->assertSame(2, (int) DB::table('stocks')->sum('stock'));
        $this->assertSame(-6, (int) DB::table('history_stocks')->sum('diff'));
        $this->assertDatabaseCount('history_stocks', 2);
    }

    public function test_shortage_leaves_stocks_and_history_unchanged(): void
    {
        $this->stock($this->warehouse, 3);

        try {
            Stock::subStocks($this->product, 4, $this->order);
            $this->fail('Insufficient stock must be rejected.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('stocks', $exception->getMessage());
        }

        $this->assertDatabaseHas('stocks', ['stock' => 3]);
        $this->assertDatabaseCount('history_stocks', 0);
    }

    public function test_return_creates_stock_at_order_warehouse(): void
    {
        Stock::diffStocks($this->item(), -5);

        $this->assertDatabaseHas('stocks', ['product_id' => $this->product, 'warehouse_id' => $this->warehouse, 'stock' => 5]);
        $this->assertDatabaseHas('history_stocks', ['order_id' => $this->order, 'diff' => 5, 'stock' => 5]);
    }

    public function test_zero_difference_does_not_create_stock_or_history(): void
    {
        Stock::diffStocks($this->item(), 0);

        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('history_stocks', 0);
    }

    public function test_stale_model_cannot_overdraw_stock(): void
    {
        $this->stock($this->warehouse, 10);
        $first = Stock::first();
        $stale = Stock::first();
        Stock::subStock($first, 6, $this->order);
        try {
            Stock::subStock($stale, 6, $this->order);
            $this->fail('Overdrawing stale stock must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Not enough', $exception->getMessage());
        }
        $this->assertDatabaseHas('stocks', ['stock' => 4]);
        $this->assertDatabaseCount('history_stocks', 1);
        Stock::addStock($stale, 2, $this->order);
        $this->assertDatabaseHas('stocks', ['stock' => 6]);
        $this->assertDatabaseHas('history_stocks', ['diff' => 2, 'stock' => 6]);
    }

    public function test_history_failure_rolls_back_entire_multiwarehouse_deduction(): void
    {
        $other = DB::table('warehouses')->insertGetId(['name' => 'Other']);
        $this->stock($this->warehouse, 2);
        $this->stock($other, 3);
        $calls = 0;
        \App\Models\HistoryStock::creating(function () use (&$calls) {
            if (++$calls === 2) {
                throw new \RuntimeException('History failure');
            }
        });
        try {
            Stock::subStocks($this->product, 4, $this->order);
            $this->fail('Expected history failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('History failure', $exception->getMessage());
        } finally {
            \App\Models\HistoryStock::flushEventListeners();
        }
        $this->assertDatabaseHas('stocks', ['warehouse_id' => $this->warehouse, 'stock' => 2]);
        $this->assertDatabaseHas('stocks', ['warehouse_id' => $other, 'stock' => 3]);
        $this->assertDatabaseCount('history_stocks', 0);
    }

    public function test_exact_deduction_removes_stock_and_logs_zero_balance(): void
    {
        $this->stock($this->warehouse, 5);
        Stock::subStocks($this->product, 5, $this->order);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseHas('history_stocks', ['stock' => 0, 'diff' => -5]);
    }

    public function test_negative_quantity_is_rejected_without_changes(): void
    {
        $this->stock($this->warehouse, 5);
        foreach (['addStock', 'subStock'] as $method) {
            try {
                Stock::$method(Stock::first(), -1, $this->order);
                $this->fail('Negative quantity must fail.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('negative', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('stocks', ['stock' => 5]);
        $this->assertDatabaseCount('history_stocks', 0);
    }

    public function test_database_rejects_duplicate_stock_rows(): void
    {
        $this->stock($this->warehouse, 3);
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->stock($this->warehouse, 4);
    }

    public function test_database_rejects_duplicate_order_items(): void
    {
        $this->item();
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->item();
    }

    public function test_database_rejects_negative_stock(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->stock($this->warehouse, -1);
    }
}
