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
        // Disable foreign key checks for this migration
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Drop the table
        Schema::dropIfExists('vegetables');
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down()
    {
        // Your rollback logic here
    }
};
