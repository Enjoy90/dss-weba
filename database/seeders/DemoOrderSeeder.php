<?php

namespace Database\Seeders;

use App\Enums\LockType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 25 demo orders with dates relative to today, so the pages always look current.
 * Includes late orders, orders near the deadline, long waiting queued orders,
 * orders in progress (auto lock) and one manual lock.
 */
class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::query()->where('email', 'manajer@spk.test')->firstOrFail();
        $leader = User::query()->where('email', 'pimpinan@spk.test')->firstOrFail();

        $customers = Customer::query()->orderBy('code')->get()->values();
        $products = Product::query()->orderBy('code')->get()->values();

        /**
         * Columns: customer index, product index, quantity, days since entered, days to deadline, status.
         * A negative deadline means the deadline has already passed.
         *
         * @var array<int, array{int, int, int, int, int, OrderStatus}>
         */
        $rows = [
            [0, 0, 300, 28, -14, OrderStatus::Done],
            [1, 3, 500, 25, -10, OrderStatus::Done],
            [2, 2, 120, 22, -8, OrderStatus::Done],
            [4, 3, 400, 20, 5, OrderStatus::Canceled],

            [0, 1, 200, 12, 4, OrderStatus::InProgress],
            [3, 1, 150, 11, 9, OrderStatus::InProgress],
            [1, 0, 250, 9, 6, OrderStatus::InProgress],
            [2, 2, 180, 8, 12, OrderStatus::InProgress],
            [0, 0, 350, 7, 8, OrderStatus::InProgress],
            [3, 1, 100, 6, 15, OrderStatus::InProgress],

            [0, 0, 220, 11, -3, OrderStatus::Queued],
            [1, 3, 600, 9, -1, OrderStatus::Queued],
            [3, 1, 90, 14, -6, OrderStatus::Queued],

            [0, 2, 160, 3, 1, OrderStatus::Queued],
            [3, 1, 130, 2, 3, OrderStatus::Queued],

            [4, 3, 800, 12, 28, OrderStatus::Queued],
            [2, 2, 140, 10, 24, OrderStatus::Queued],
            [4, 3, 450, 9, 21, OrderStatus::Queued],

            [1, 0, 210, 4, 18, OrderStatus::Queued],
            [0, 1, 175, 3, 14, OrderStatus::Queued],
            [2, 0, 260, 2, 20, OrderStatus::Queued],
            [3, 1, 110, 2, 16, OrderStatus::Queued],
            [1, 2, 190, 1, 22, OrderStatus::Queued],
            [0, 3, 700, 1, 25, OrderStatus::Queued],
            [4, 3, 520, 0, 30, OrderStatus::Queued],
        ];

        $manualLockRow = 18;

        foreach ($rows as $index => [$customer, $product, $quantity, $enteredAgo, $dueIn, $status]) {
            $order = ProductionOrder::query()->make([
                'customer_id' => $customers[$customer]->id,
                'product_id' => $products[$product]->id,
                'quantity' => $quantity,
                'order_date' => today()->subDays($enteredAgo),
                'deadline_date' => today()->addDays($dueIn),
                'unit_price' => $products[$product]->unit_price,
                'status' => $status,
                'created_by' => $manager->id,
            ]);

            if ($status === OrderStatus::InProgress) {
                $order->lock_type = LockType::Auto;
                $order->locked_at = now();
            }

            if ($index === $manualLockRow) {
                $order->lock_type = LockType::Manual;
                $order->lock_reason = 'Permintaan langsung dari pimpinan';
                $order->locked_by = $leader->id;
                $order->locked_at = now();
                $order->lock_until = today()->addDays(7);
            }

            $order->save();
        }
    }
}
