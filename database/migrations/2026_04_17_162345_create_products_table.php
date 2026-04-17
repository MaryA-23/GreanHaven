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
        $table->id();
        $table->string('name'); 
        $table->enum('status', ['ready', 'not_ready'])->default('not_ready');
        $table->foreignId('category_id')->constrained()->cascadeOnDelete();
        $table->softDeletes(); // adds deleted_at column
         $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
