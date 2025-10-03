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
            if (!Schema::hasColumn('orders', 'vegetable_request_id')) {
            $table->unsignedBigInteger('vegetable_request_id')->nullable()->after('user_id');
            $table->foreign('vegetable_request_id')
                ->references('id')
                ->on('vegetable_requests')
                ->onDelete('set null');
        }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('orders', function (Blueprint $table) {
        if (Schema::hasColumn('orders', 'vegetable_request_id')) {
            $table->dropForeign(['vegetable_request_id']);
            $table->dropColumn('vegetable_request_id');
        }
    });
    }
};
