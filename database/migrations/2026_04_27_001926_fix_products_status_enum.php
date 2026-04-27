<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE products
            MODIFY status ENUM(
                'active',
                'inactive',
                'low_stock',
                'out_of_stock'
            ) NOT NULL DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE products
            MODIFY status ENUM(
                'active',
                'inactive'
            ) NOT NULL DEFAULT 'active'
        ");
    }
};