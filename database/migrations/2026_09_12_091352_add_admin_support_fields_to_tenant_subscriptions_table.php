<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->string('source', 30)
                ->default('paystack')
                ->after('status');

            $table->text('admin_note')
                ->nullable()
                ->after('source');

            $table->string('granted_by_uuid')
                ->nullable()
                ->after('admin_note');

            $table->unsignedBigInteger('source_subscription_id')
                ->nullable()
                ->after('granted_by_uuid');

            $table->index('source');
            $table->index('granted_by_uuid');
            $table->index('source_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropIndex(['granted_by_uuid']);
            $table->dropIndex(['source_subscription_id']);

            $table->dropColumn([
                'source',
                'admin_note',
                'granted_by_uuid',
                'source_subscription_id',
            ]);
        });
    }
};
