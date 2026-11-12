<?php

// Independent process: never use the application's working database for concurrency tests.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['config']->set('database.default', 'mysql');
$app['config']->set('database.connections.mysql', json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR));
$config = $app['config']->get('database.connections.mysql');
if ($config['host'] !== 'mysql-test' || $config['database'] !== 'maxmollcrm_test' || ! empty($config['url'])) {
    throw new RuntimeException('Unsafe concurrency test connection.');
}
Illuminate\Support\Facades\DB::purge('mysql');
echo "ready\n";
fflush(STDOUT);
// Parent releases both workers together after they have booted.
if (trim(fgets(STDIN)) !== 'go') {
    exit(2);
}
try {
    [$mode, $product, $warehouse, $order] = array_slice($argv, 2);
    $status = 200;
    if ($mode === 'deduct') {
        App\Models\Stock::subStocks((int) $product, 6, (int) $order);
    } elseif ($mode === 'return') {
        $stock = new App\Models\Stock(['product_id' => $product, 'warehouse_id' => $warehouse]);
        App\Models\Stock::addStock($stock, 3, (int) $order);
    } elseif ($mode === 'update') {
        $request = Illuminate\Http\Request::create('/api/v1/order', 'PUT', server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode(['id' => (int) $order, 'items' => [['id' => (int) $product, 'count' => 7]]]));
        $status = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request)->getStatusCode();
    } else {
        $action = match ($mode) {
            'cancel' => new App\Actions\Order\CancelAction,
            'resume' => new App\Actions\Order\ResumeAction,
            'complete' => new App\Actions\Order\CompleteAction,
        };
        $status = $action((int) $order)->getStatusCode();
    }
    echo 'result:'.$status."\n";
} catch (RuntimeException $exception) {
    if (! str_contains($exception->getMessage(), 'Not enough stocks')) {
        throw $exception;
    }
    echo "result:400\n";
}
