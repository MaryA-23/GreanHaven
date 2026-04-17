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
        Schema::table('orders', function (Blueprint $table) {
            
        $table->dropForeign(['vegetable_request_id']);
        $table->dropColumn('vegetable_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('vegetable_request_id')->nullable();
              $table->foreign('vegetable_request_id')
              ->references('id')
              ->on('vegetable_requests')
              ->onDelete('cascade');
        });
    }
};
