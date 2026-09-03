<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Step 1:
         * Convert the old ENUM column to VARCHAR temporarily.
         *
         * Using raw SQL here avoids Doctrine DBAL trying to inspect
         * the existing MySQL ENUM column.
         */
        DB::statement("
            ALTER TABLE products
            MODIFY COLUMN status VARCHAR(255) NULL
        ");

        /*
         * Step 2:
         * Convert old status values to the new values.
         */
        DB::table('products')
            ->where('status', 'ready')
            ->update(['status' => 'active']);

        DB::table('products')
            ->where('status', 'not_ready')
            ->update(['status' => 'inactive']);

        /*
         * Any unexpected status should become inactive.
         */
        DB::table('products')
            ->whereNotNull('status')
            ->whereNotIn('status', [
                'active',
                'inactive',
                'out_of_stock'
            ])
            ->update([
                'status' => 'inactive'
            ]);

        /*
         * Null values also become inactive because the final
         * column will be NOT NULL.
         */
        DB::table('products')
            ->whereNull('status')
            ->update([
                'status' => 'inactive'
            ]);

        /*
         * Step 3:
         * Convert VARCHAR back to the new ENUM.
         */
        DB::statement("
            ALTER TABLE products
            MODIFY COLUMN status
            ENUM('active', 'inactive', 'out_of_stock')
            NOT NULL DEFAULT 'inactive'
        ");
    }

    public function down(): void
    {
        /*
         * Convert ENUM to VARCHAR first.
         */
        DB::statement("
            ALTER TABLE products
            MODIFY COLUMN status VARCHAR(255) NULL
        ");

        /*
         * Convert new statuses back to the old statuses.
         */
        DB::table('products')
            ->where('status', 'active')
            ->update([
                'status' => 'ready'
            ]);

        DB::table('products')
            ->whereIn('status', [
                'inactive',
                'out_of_stock'
            ])
            ->update([
                'status' => 'not_ready'
            ]);

        /*
         * Restore old ENUM.
         */
        DB::statement("
            ALTER TABLE products
            MODIFY COLUMN status
            ENUM('ready', 'not_ready')
            NOT NULL DEFAULT 'not_ready'
        ");
    }
};