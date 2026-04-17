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
        Schema::table('vegetable_requests', function (Blueprint $table) {
            $table->dropForeign(['vegetable_id']);        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vegetable_requests', function (Blueprint $table) {
            $table->foreign('vegetable_id')->references('id')->on('vegetables');
        });
    }
};
