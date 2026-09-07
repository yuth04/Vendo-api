<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('return_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Financial Details
            $table->decimal('amount', 10, 2);
            $table->string('method')->default('original_payment'); // e.g., khqr, store_credit, bank_transfer
            $table->string('provider')->nullable();               // e.g., bakong, stripe, internal
            $table->string('status')->default('pending');         // pending, completed, failed

            // Transaction Audit & Gateway Traces
            $table->string('refund_reference')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
