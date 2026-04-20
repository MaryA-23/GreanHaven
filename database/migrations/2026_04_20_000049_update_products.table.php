<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // 1. Safe data cleanup first
        DB::statement("UPDATE products SET status = 'active' WHERE status = 'ready'");
        DB::statement("UPDATE products SET status = 'inactive' WHERE status = 'not_ready' OR status NOT IN ('active', 'inactive', 'out_of_stock')");

        // 2. Verify data
        $statuses = DB::table('products')->select('status')->distinct()->pluck('status');
        if ($statuses->contains('ready') || $statuses->contains('not_ready')) {
            throw new \Exception('Data cleanup failed');
        }

        // 3. Change enum
        Schema::table('products', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'out_of_stock'])
                  ->default('inactive')
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('status', ['ready', 'not_ready'])->change();
        });
    }
};
