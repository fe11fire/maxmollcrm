<?php

namespace Database\Factories;

use App\Models\Warehouse;
use App\Services\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $warehouses = Warehouse::pluck('id')->toArray();
        return [
            'customer' => fake()->name(),
            'warehouse_id' => fake()->randomElement($warehouses),
            'status' => fake()->randomElement(OrderStatus::cases()),
        ];
    }
}
