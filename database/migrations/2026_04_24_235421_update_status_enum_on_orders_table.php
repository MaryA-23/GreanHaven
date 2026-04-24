<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE orders 
            MODIFY status ENUM(
                'pending_payment',
                'paid',
                'processing',
                'completed',
                'cancelled',
                'expired'
            ) NOT NULL DEFAULT 'pending_payment'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE orders 
            MODIFY status ENUM(
                'pending',
                'confirmed',
                'completed',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'
        ");
    }
};
