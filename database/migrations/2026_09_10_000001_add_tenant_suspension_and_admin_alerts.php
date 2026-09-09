<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false);
            $table->text('suspension_reason')->nullable();
        });

        Schema::create('admin_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->text('message');
            $table->string('path');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('admin_alert_reads', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('alert_id');
            $table->timestamp('read_at')->useCurrent();

            $table->primary(['admin_id', 'alert_id']);

            $table->foreign('alert_id')
                ->references('id')
                ->on('admin_alerts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alert_reads');
        Schema::dropIfExists('admin_alerts');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'is_suspended',
                'suspension_reason',
            ]);
        });
    }
};