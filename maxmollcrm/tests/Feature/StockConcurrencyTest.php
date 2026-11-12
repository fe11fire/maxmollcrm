<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StockConcurrencyTest extends TestCase
{
    // Workers must see committed fixtures, so do not wrap this class in RefreshDatabase's transaction.
    use DatabaseMigrations;

    private int $product;

    private int $warehouse;

    private int $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = DB::table('products')->insertGetId(['name' => 'Concurrent product', 'price' => 1]);
        $this->warehouse = DB::table('warehouses')->insertGetId(['name' => 'Warehouse']);
        $this->order = DB::table('orders')->insertGetId(['customer' => 'Customer', 'warehouse_id' => $this->warehouse]);
    }

    private function race(string $first, string $second): array
    {
        $workers = [];
        $inputs = [];
        // Hold the relevant lock until both independent connections have started.
        DB::beginTransaction();
        if (in_array($first, ['cancel', 'resume', 'complete'])) {
            DB::table('orders')->where('id', $this->order)->lockForUpdate()->first();
        } else {
            DB::table('products')->where('id', $this->product)->lockForUpdate()->first();
        }
        try {
            foreach ([$first, $second] as $mode) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/stock-worker.php'),
                    json_encode(config('database.connections.mysql'), JSON_THROW_ON_ERROR),
                    $mode, (string) $this->product, (string) $this->warehouse, (string) $this->order]);
                $input = new InputStream;
                $process->setInput($input)->setTimeout(20)->start();
                $workers[] = $process;
                $inputs[] = $input;
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $process->getErrorOutput());
            }
            foreach ($inputs as $input) {
                $input->write("go\n");
            }
            $deadline = microtime(true) + 5;
            do {
                foreach ($workers as $worker) {
                    $worker->getIncrementalOutput(); // Flush the queued stdin signal to each process.
                }
                $waiting = (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.PROCESSLIST WHERE USER = 'test' AND ID <> CONNECTION_ID() AND INFO LIKE '%for update%'")->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Both workers must reach the held row lock before it is released.');
            DB::commit();
            $statuses = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $this->assertMatchesRegularExpression('/result:(200|400)/', $worker->getOutput());
                preg_match('/result:(\d+)/', $worker->getOutput(), $matches);
                $statuses[] = (int) $matches[1];
            }
            sort($statuses);

            return $statuses;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($inputs as $input) {
                $input->close();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(0);
                }
            }
        }
    }

    public function test_two_deductions_cannot_oversell(): void
    {
        DB::table('stocks')->insert(['product_id' => $this->product, 'warehouse_id' => $this->warehouse, 'stock' => 10]);
        $this->assertSame([200, 400], $this->race('deduct', 'deduct'));
        $this->assertDatabaseHas('stocks', ['stock' => 4]);
        $this->assertDatabaseCount('history_stocks', 1);
        $this->assertDatabaseHas('history_stocks', ['stock' => 4, 'diff' => -6]);
    }

    public function test_concurrent_returns_create_one_row_with_correct_history(): void
    {
        $this->assertSame([200, 200], $this->race('return', 'return'));
        $this->assertDatabaseCount('stocks', 1);
        $this->assertDatabaseHas('stocks', ['stock' => 6]);
        $this->assertSame([3, 6], DB::table('history_stocks')->orderBy('id')->pluck('stock')->all());
    }

    public function test_two_cancellations_return_goods_once(): void
    {
        DB::table('order_items')->insert(['product_id' => $this->product, 'order_id' => $this->order, 'count' => 5]);
        $this->assertSame([200, 400], $this->race('cancel', 'cancel'));
        $this->assertDatabaseHas('stocks', ['stock' => 5]);
        $this->assertDatabaseCount('history_stocks', 1);
        $this->assertDatabaseHas('orders', ['status' => 'canceled']);
    }

    public function test_cancel_and_complete_cannot_both_succeed(): void
    {
        DB::table('order_items')->insert(['product_id' => $this->product, 'order_id' => $this->order, 'count' => 5]);
        $this->assertSame([200, 400], $this->race('cancel', 'complete'));
        $status = DB::table('orders')->value('status');
        $this->assertSame($status === 'canceled' ? 5 : 0, (int) DB::table('stocks')->sum('stock'));
        $this->assertDatabaseCount('history_stocks', $status === 'canceled' ? 1 : 0);
    }

    public function test_two_resumes_reserve_goods_once(): void
    {
        DB::table('orders')->update(['status' => 'canceled']);
        DB::table('order_items')->insert(['product_id' => $this->product, 'order_id' => $this->order, 'count' => 5]);
        DB::table('stocks')->insert(['product_id' => $this->product, 'warehouse_id' => $this->warehouse, 'stock' => 10]);
        $this->assertSame([200, 400], $this->race('resume', 'resume'));
        $this->assertDatabaseHas('stocks', ['stock' => 5]);
        $this->assertDatabaseCount('history_stocks', 1);
    }

    public function test_edit_and_cancel_keep_inventory_consistent(): void
    {
        DB::table('order_items')->insert(['product_id' => $this->product, 'order_id' => $this->order, 'count' => 5]);
        DB::table('stocks')->insert(['product_id' => $this->product, 'warehouse_id' => $this->warehouse, 'stock' => 5]);
        $statuses = $this->race('cancel', 'update');
        $this->assertContains($statuses, [[200, 200], [200, 400]]);
        $this->assertDatabaseHas('orders', ['status' => 'canceled']);
        $this->assertDatabaseHas('stocks', ['stock' => 10]);
        $this->assertSame(5, (int) DB::table('history_stocks')->sum('diff'));
    }
}
