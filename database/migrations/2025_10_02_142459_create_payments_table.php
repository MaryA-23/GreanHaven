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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade'); // Links to orders table
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Optional: for direct user reference
            $table->decimal('amount', 10, 2); // Payment amount (matches order total_price)
            $table->enum('status', ['unpaid', 'paid', 'pending', 'failed'])->default('unpaid'); // Simple status now; expandable later
            $table->string('payment_method')->nullable(); // e.g., 'card', 'bank_transfer', 'paystack', etc. (for future)
            $table->string('transaction_id')->nullable(); // Reference ID from gateway (e.g., Paystack ref)
            $table->timestamp('paid_at')->nullable(); // When payment was completed
            $table->text('notes')->nullable(); // Optional notes or error messages
            $table->timestamps();
            // Indexes for performance
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
