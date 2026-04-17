<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Mark REMAINING Pending vegetable migrations as Ran
        $pendingVegetableMigrations = [
            // None pending for vegetables anymore - all good!
        ];

        // 2. Mark the duplicate column migration as Ran
        DB::table('migrations')->updateOrInsert(
            ['migration' => '2025_10_01_121349_add_company_and_role_to_users'],
            ['batch' => 1]
        );

        // 3. Drop vegetable tables (safe if they don't exist)
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        
        if (Schema::hasTable('vegetables')) {
            Schema::drop('vegetables');
        }
        if (Schema::hasTable('vegetable_requests')) {
            Schema::drop('vegetable_requests');
        }
        
        // 4. Clean up any vegetable foreign keys
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'vegetable_request_id')) {
                $table->dropForeign(['vegetable_request_id']);
                $table->dropColumn('vegetable_request_id');
            }
        });
        
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'vegetable_id')) {
                $table->dropForeign(['vegetable_id']);
                $table->dropColumn('vegetable_id');
            }
        });
        
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(): void
    {
        //
    }
};