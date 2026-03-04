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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('payment_method');
            // example: aba, card, paypal, cash

            $table->decimal('amount', 10, 2);

            $table->string('transaction_id')->nullable();
            // from payment gateway

            $table->enum('status', [
                'pending',
                'success',
                'failed',
                'refunded'
            ])->default('pending');

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
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
