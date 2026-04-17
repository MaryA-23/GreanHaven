<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::dropIfExists('vegetable_requests');
}

public function down()
{
    Schema::create('vegetable_requests', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
}
};
