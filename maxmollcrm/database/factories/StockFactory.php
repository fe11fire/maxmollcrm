<?php

namespace Database\Factories;

use Exception;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $warehouses = Warehouse::pluck('id')->toArray();
        $products = Product::pluck('id')->toArray();

        if (
            (count($products) == 0) ||
            (count($warehouses) == 0)
        ) {
            throw new Exception("empty StockFactory fks", 1);
        }

        return [
            'warehouse_id' => fake()->randomElement($warehouses),
            'product_id' => fake()->randomElement($products),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}
