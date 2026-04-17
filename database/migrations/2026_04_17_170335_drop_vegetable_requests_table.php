<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        Schema::dropIfExists('vegetable_requests');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    public function down()
    {
        //
    }
};
