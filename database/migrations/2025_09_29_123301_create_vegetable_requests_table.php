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
        Schema::create('vegetable_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vegetable_id')->constrained()->onDelete('cascade');
            $table->string('customer_name');
            $table->string('customer_contact');
            $table->enum('status', ['pending', 'in_progress', 'fulfilled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vegetable_requests');
    }
};
