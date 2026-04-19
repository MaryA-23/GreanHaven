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
        // 1. Map old → new values
        DB::statement("UPDATE products SET status = 'active' WHERE status = 'ready'");
        DB::statement("UPDATE products SET status = 'inactive' WHERE status = 'not_ready'");
        
        // 2. Change column to new enum
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
