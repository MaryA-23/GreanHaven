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
        Schema::table('products', function (Blueprint $table) {
        $table->decimal('price', 10, 2)->after('status');
        $table->integer('quantity')->default(0);
        $table->text('description')->nullable();
        $table->string('unit')->nullable(); // kg, bunch, crate
        $table->boolean('is_available')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
            'price',
            'quantity',
            'description',
            'unit',
            'is_available'
        ]);
        });
    }
};
