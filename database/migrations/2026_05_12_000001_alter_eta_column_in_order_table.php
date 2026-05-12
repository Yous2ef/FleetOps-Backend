<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Migration: alter_eta_column_in_order_table
 * Changes ETA from char(10) to datetime2(7) for proper datetime storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Clear any existing HH:MM values that can't be cast to datetime2
        \Illuminate\Support\Facades\DB::statement("UPDATE [order] SET [ETA] = NULL");

        // Alter column from char(10) to datetime2(7)
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE [order] ALTER COLUMN [ETA] datetime2(7) NULL"
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("UPDATE [order] SET [ETA] = NULL");
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE [order] ALTER COLUMN [ETA] char(10) NULL"
        );
    }
};
