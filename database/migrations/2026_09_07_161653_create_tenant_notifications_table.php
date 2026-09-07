<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('type');

            $table->string('title');

            $table->text('message');

            $table->string('action_url')
                ->nullable();

            $table->unsignedBigInteger('reference_id')
                ->nullable();

            $table->boolean('is_read')
                ->default(false);

            $table->timestamp('read_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'company_id',
                'is_read'
            ]);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'tenant_notifications'
        );
    }
};