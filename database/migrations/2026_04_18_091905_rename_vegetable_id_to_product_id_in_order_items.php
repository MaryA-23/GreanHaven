<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
        {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['vegetable_id']);
            $table->renameColumn('vegetable_id', 'product_id');
            $table->foreign('product_id')->references('id')->on('products');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
             $table->dropForeign(['product_id']);
            $table->renameColumn('product_id', 'vegetable_id');
            $table->foreign('vegetable_id')->references('id')->on('vegetables');
        });
    }
};
