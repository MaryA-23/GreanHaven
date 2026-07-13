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
        Schema::table('users', function (Blueprint $table) {
         $table->string('phone')->nullable()->after('email');
        $table->string('status')->default('Active')->after('role');
        $table->timestamp('last_login')->nullable()->after('remember_token');
        $table->string('gender')->nullable()->after('last_login');
        $table->string('profile_picture')->nullable()->after('gender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
