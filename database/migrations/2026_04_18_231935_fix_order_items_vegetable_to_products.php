<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up()
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Drop old foreign key
            $table->dropForeign(['vegetable_id']);
            // Rename column
            $table->renameColumn('vegetable_id', 'product_id');
            // New foreign key to products
            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->renameColumn('product_id', 'vegetable_id');
        });
    }
};
