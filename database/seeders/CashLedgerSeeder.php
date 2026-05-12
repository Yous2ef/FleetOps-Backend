<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CashLedgerSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Get all drivers and orders
        $drivers = DB::table('drivers')->pluck('driver_id')->toArray();
        $orders = DB::table('order')->pluck('OrderID')->toArray();

        if (empty($drivers) || empty($orders)) {
            $this->command->warn('⚠️ No drivers or orders found. Skipping CashLedgerSeeder.');
            return;
        }

        $paymentMethods = ['cash', 'card', 'digital_wallet', 'credit'];
        $statuses = ['pending', 'collected', 'failed', 'refunded'];

        $count = 0;
        $orderChunks = array_chunk($orders, ceil(count($orders) / 3));

        for ($i = 0; $i < 3; $i++) {
            $month = now()->subMonths($i);
            $chunk = $orderChunks[$i] ?? [];

            foreach ($chunk as $orderId) {
                $driverId = $drivers[array_rand($drivers)];
                $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
                $paymentStatus = $statuses[array_rand($statuses)];
                // Force some to be 'collected' so the chart shows data
                // Month 1 (index 1) will have a "loss" (much less collected revenue)
                $chance = ($i === 1) ? 8 : 2; 
                if (rand(0, 10) > $chance) {
                    $paymentStatus = 'collected';
                }
                $amountCollected = rand(500, 5000) + rand(0, 99) / 100;

                DB::table('cash_ledger')->updateOrInsert(
                    ['order_id' => $orderId],
                    [
                        'driver_id'              => $driverId,
                        'amount_collected'       => $amountCollected,
                        'payment_method'         => $paymentMethod,
                        'payment_status'         => $paymentStatus,
                        'transaction_ts'         => $month->copy()->subDays(rand(0, 28))->toDateTimeString(),
                        'handed_over_to_company' => $paymentStatus === 'collected' ? rand(0, 1) : null,
                        'created_at'             => $now,
                        'updated_at'             => $now,
                    ]
                );
                $count++;
            }
        }

        $this->command->info("✅ CashLedgerSeeder: $count cash ledger entries created.");
    }
}
