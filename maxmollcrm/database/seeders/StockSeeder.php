<?php

namespace Database\Seeders;

use App\Models\HistoryStock;
use Exception;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = Warehouse::pluck('id')->toArray();
        $products = Product::pluck('id')->toArray();

        if (
            (count($products) == 0) ||
            (count($warehouses) == 0)
        ) {
            throw new Exception("empty StockFactory fks", 1);
        }

        for ($i = 0; $i < 50; $i++) {
            $warehouse_id = fake()->randomElement($warehouses);
            $product_id = fake()->randomElement($products);
            // $stock = random_int(1, 100);
            $stock = 5;

            if (Stock::where('warehouse_id', $warehouse_id)->where('product_id', $product_id)->exists()) {
                continue;
            }


            Stock::create([
                'warehouse_id' => $warehouse_id,
                'product_id' => $product_id,
                'stock' => $stock,
            ]);

            HistoryStock::create(
                [
                    'warehouse_id' => $warehouse_id,
                    'product_id' => $product_id,
                    'stock' => $stock,
                    'diff' => $stock,
                ]
            );
        }
    }
}
