<?php

/**
 * @file: BackfillOrderTrackingTokens.php
 * @description: One-time Artisan command to fix all orders that still have the old
 *               hardcoded URL ("http://fleetops.com/track/{id}") or a null value
 *               in their LiveTrackingLink column.
 *
 *               Run with:  php artisan orders:backfill-tracking-tokens
 *
 *               What it does per order:
 *                 1. Generates a new UUID token.
 *                 2. Updates LiveTrackingLink on the order row.
 *                 3. Inserts a corresponding tracking_tokens row with a 30-day expiry
 *                    (skips if the token already exists in that table).
 *
 * @module: OrderManagement
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillOrderTrackingTokens extends Command
{
    protected $signature   = 'orders:backfill-tracking-tokens
                              {--dry-run : Show what would be updated without writing to DB}';

    protected $description = 'Backfill UUID tracking tokens for orders that have a missing or '
                           . 'hardcoded LiveTrackingLink (e.g. http://fleetops.com/track/ID).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written to the database.');
        }

        // Find orders that need fixing:
        //   - NULL LiveTrackingLink, OR
        //   - LiveTrackingLink that looks like a URL (old hardcoded format)
        $orders = DB::table('order')
            ->whereNull('LiveTrackingLink')
            ->orWhere('LiveTrackingLink', 'like', 'http://%')
            ->orWhere('LiveTrackingLink', 'like', 'https://%')
            ->select('OrderID', 'LiveTrackingLink')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('✓ All orders already have valid UUID tracking tokens. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Found {$orders->count()} order(s) to fix.");
        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        $fixed  = 0;
        $errors = 0;

        foreach ($orders as $order) {
            try {
                $token = (string) Str::uuid();

                if (! $dryRun) {
                    // 1. Update the order row
                    DB::table('order')
                        ->where('OrderID', $order->OrderID)
                        ->update(['LiveTrackingLink' => $token]);

                    // 2. Upsert tracking_tokens row (skip if token somehow exists)
                    $exists = DB::table('tracking_tokens')
                        ->where('order_id', $order->OrderID)
                        ->exists();

                    if (! $exists) {
                        DB::table('tracking_tokens')->insert([
                            'token'      => $token,
                            'order_id'   => $order->OrderID,
                            'expires_at' => now()->addDays(30),
                            'created_at' => now(),
                        ]);
                    }
                } else {
                    $this->line(
                        "\n  OrderID {$order->OrderID}: "
                        . "'{$order->LiveTrackingLink}' → '{$token}'"
                    );
                }

                $fixed++;
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("  OrderID {$order->OrderID} failed: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("DRY RUN complete. Would have fixed {$fixed} order(s).");
        } else {
            $this->info("✓ Fixed {$fixed} order(s) successfully.");
            if ($errors > 0) {
                $this->warn("  {$errors} order(s) failed — check the output above.");
            }
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
