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
        Schema::table('vegetables', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('unit')->default('kg');
            $table->boolean('is_available')->default(true);
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vegetables', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
