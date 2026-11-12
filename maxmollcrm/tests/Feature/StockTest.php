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
}
