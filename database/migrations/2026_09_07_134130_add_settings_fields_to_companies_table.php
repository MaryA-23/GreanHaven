<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {

            if (!Schema::hasColumn('companies', 'phone')) {
                $table->string('phone')->nullable();
            }

            if (!Schema::hasColumn('companies', 'address')) {
                $table->string('address')->nullable();
            }

            if (!Schema::hasColumn('companies', 'city')) {
                $table->string('city')->nullable();
            }

            if (!Schema::hasColumn('companies', 'currency')) {
                $table->string('currency', 10)->default('GHS');
            }

            if (!Schema::hasColumn('companies', 'notification_settings')) {
                $table->json('notification_settings')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {

            $columns = [];

            if (Schema::hasColumn('companies', 'phone')) {
                $columns[] = 'phone';
            }

            if (Schema::hasColumn('companies', 'address')) {
                $columns[] = 'address';
            }

            if (Schema::hasColumn('companies', 'city')) {
                $columns[] = 'city';
            }

            if (Schema::hasColumn('companies', 'currency')) {
                $columns[] = 'currency';
            }

            if (Schema::hasColumn('companies', 'notification_settings')) {
                $columns[] = 'notification_settings';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};