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
        // Remove the old vegetable request relationship from orders first.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['vegetable_request_id']);
            $table->dropColumn('vegetable_request_id');
        });

        // vegetable_requests depends on vegetables,
        // so it must be dropped before vegetables.
        Schema::dropIfExists('vegetable_requests');

        // Old vegetable table is no longer used.
        Schema::dropIfExists('vegetables');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('vegetables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['ready', 'not_ready'])->default('not_ready');
            $table->timestamps();
            $table->softDeletes();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('unit')->default('kg');
            $table->boolean('is_available')->default(true);
        });

        Schema::create('vegetable_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vegetable_id')
                ->constrained('vegetables')
                ->cascadeOnDelete();

            $table->string('customer_name');
            $table->string('customer_contact');

            $table->enum('status', [
                'pending',
                'in_progress',
                'fulfilled'
            ])->default('pending');

            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('vegetable_request_id')
                ->nullable()
                ->constrained('vegetable_requests')
                ->nullOnDelete();
        });
    }
};