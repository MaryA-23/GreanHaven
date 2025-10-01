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
            if (!Schema::hasColumn('vegetables', 'quantity')) {
                $table->integer('quantity')->default(0);
            }
            if (!Schema::hasColumn('vegetables', 'category')) {
                $table->string('category')->nullable();
            }
            if (!Schema::hasColumn('vegetables', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('vegetables', 'unit')) {
                $table->string('unit')->default('kg');
            }
            if (!Schema::hasColumn('vegetables', 'is_available')) {
                $table->boolean('is_available')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vegetables', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'category', 'description', 'unit', 'is_available']);
        });
    }
};
