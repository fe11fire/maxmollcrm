<?php

namespace Tests\Feature;

use App\Models\HistoryStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderStockTest extends TestCase
{
    use RefreshDatabase;

    private int $product;

    private int $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->warehouse = DB::table('warehouses')->insertGetId(['name' => 'Warehouse']);
        $this->product = DB::table('products')->insertGetId(['name' => 'Product', 'price' => 10]);
        DB::table('stocks')->insert(['product_id' => $this->product, 'warehouse_id' => $this->warehouse, 'stock' => 10]);
    }

    private function createOrder(int $count = 5): int
    {
        $this->postJson('/api/v1/order', ['customer' => 'Customer', 'warehouse_id' => $this->warehouse,
            'items' => [['id' => $this->product, 'count' => $count]]])->assertOk();

        return (int) DB::table('orders')->max('id');
    }

    public function test_edit_remove_cancel_resume_and_complete_preserve_inventory(): void
    {
        $id = $this->createOrder();
        $this->assertDatabaseHas('stocks', ['stock' => 5]);
        $this->putJson('/api/v1/order', ['id' => $id, 'items' => [['id' => $this->product, 'count' => 7]]])->assertOk();
        $this->assertDatabaseHas('stocks', ['stock' => 3]);
        $this->putJson('/api/v1/order', ['id' => $id, 'items' => [['id' => $this->product, 'count' => 2]]])->assertOk();
        $this->assertDatabaseHas('stocks', ['stock' => 8]);
        $this->putJson('/api/v1/order/cancel', ['id' => $id])->assertOk();
        $this->assertDatabaseHas('stocks', ['stock' => 10]);
        $this->putJson('/api/v1/order/cancel', ['id' => $id])->assertStatus(400);
        $this->putJson('/api/v1/order/complete', ['id' => $id])->assertStatus(400);
        $this->putJson('/api/v1/order', ['id' => $id, 'customer' => 'Other'])->assertStatus(400);
        $this->putJson('/api/v1/order/resume', ['id' => $id])->assertOk();
        $this->assertDatabaseHas('stocks', ['stock' => 8]);
        $this->putJson('/api/v1/order/resume', ['id' => $id])->assertStatus(400);
        $this->putJson('/api/v1/order', ['id' => $id, 'items' => []])->assertOk();
        $this->assertDatabaseHas('stocks', ['stock' => 10]);
        $this->assertDatabaseCount('order_items', 0);
        $movements = DB::table('history_stocks')->count();
        $this->putJson('/api/v1/order/complete', ['id' => $id])->assertOk();
        $this->assertNotNull(DB::table('orders')->where('id', $id)->value('completed_at'));
        foreach (['complete', 'cancel', 'resume'] as $action) {
            $this->putJson('/api/v1/order/'.$action, ['id' => $id])->assertStatus(400);
        }
        $this->assertDatabaseCount('history_stocks', $movements);
    }

    public function test_create_shortage_rolls_back_order_and_previous_product(): void
    {
        $missing = DB::table('products')->insertGetId(['name' => 'Missing', 'price' => 1]);
        $this->postJson('/api/v1/order', ['customer' => 'Customer', 'items' => [
            ['id' => $this->product, 'count' => 5], ['id' => $missing, 'count' => 1],
        ]])->assertStatus(400);
        $this->assertDatabaseHas('stocks', ['stock' => 10]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('history_stocks', 0);
    }

    public function test_cancel_failure_rolls_back_returns_and_status(): void
    {
        $id = $this->createOrder();
        $other = DB::table('products')->insertGetId(['name' => 'Other', 'price' => 1]);
        DB::table('order_items')->insert(['order_id' => $id, 'product_id' => $other, 'count' => 2]);
        $calls = 0;
        HistoryStock::creating(function () use (&$calls) {
            if (++$calls === 2) {
                throw new \RuntimeException('Return failed');
            }
        });
        try {
            $this->putJson('/api/v1/order/cancel', ['id' => $id])->assertStatus(400);
        } finally {
            HistoryStock::flushEventListeners();
        }
        $this->assertDatabaseHas('orders', ['id' => $id, 'status' => 'active']);
        $this->assertDatabaseHas('stocks', ['product_id' => $this->product, 'stock' => 5]);
        $this->assertDatabaseMissing('stocks', ['product_id' => $other]);
        $this->assertDatabaseCount('history_stocks', 1);
    }

    public function test_resume_shortage_keeps_order_canceled(): void
    {
        $id = $this->createOrder();
        $this->putJson('/api/v1/order/cancel', ['id' => $id])->assertOk();
        DB::table('stocks')->update(['stock' => 1]);
        $this->putJson('/api/v1/order/resume', ['id' => $id])->assertStatus(400);
        $this->assertDatabaseHas('orders', ['id' => $id, 'status' => 'canceled']);
        $this->assertDatabaseHas('stocks', ['stock' => 1]);
        $this->assertDatabaseCount('history_stocks', 2);
    }

    public function test_invalid_quantities_and_duplicate_products_are_rejected(): void
    {
        foreach ([null, 0, -1, 1.5, 'abc', 2147483648] as $quantity) {
            $this->postJson('/api/v1/order', ['customer' => 'Customer', 'items' => [
                ['id' => $this->product, 'count' => $quantity],
            ]])->assertUnprocessable()->assertJsonValidationErrors('items.0.count');
        }
        $this->postJson('/api/v1/order', ['customer' => 'Customer', 'items' => [
            ['id' => $this->product],
        ]])->assertUnprocessable()->assertJsonValidationErrors('items.0.count');
        $items = array_fill(0, 2, ['id' => $this->product, 'count' => 1]);
        $this->postJson('/api/v1/order', ['customer' => 'Customer', 'items' => $items])->assertUnprocessable();
        $id = $this->createOrder();
        $this->putJson('/api/v1/order', ['id' => $id, 'items' => $items])->assertUnprocessable();
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_seeders_populate_stocks_with_foreign_keys(): void
    {
        $this->seed();
        $this->assertGreaterThan(1, DB::table('stocks')->count());
    }

    public function test_get_requests_cannot_change_order_status(): void
    {
        $id = $this->createOrder();
        foreach (['cancel', 'resume', 'complete'] as $action) {
            $this->getJson('/api/v1/order/'.$id.'/'.$action)->assertNotFound();
        }
        $this->assertDatabaseHas('orders', ['id' => $id, 'status' => 'active']);
        $this->assertDatabaseHas('stocks', ['stock' => 5]);
        $this->assertDatabaseCount('history_stocks', 1);
    }
}
