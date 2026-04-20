<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Temporarily make nullable to avoid enum issues
            $table->string('status')->nullable()->change();
        });

        // Safe data migration
        DB::table('products')
            ->where('status', 'ready')
            ->update(['status' => 'active']);

        DB::table('products')
            ->where('status', 'not_ready')
            ->update(['status' => 'inactive']);

        DB::table('products')
            ->whereNotIn('status', ['active', 'inactive', 'out_of_stock'])
            ->update(['status' => 'inactive']);

        // New enum
        Schema::table('products', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'out_of_stock'])
                  ->default('inactive')
                  ->nullable(false)
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