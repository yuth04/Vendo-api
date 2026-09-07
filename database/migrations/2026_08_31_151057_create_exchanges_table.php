<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exchanges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('return_order_id')
                ->unique()
                ->constrained('return_orders')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('status')->default('processing');

            $table->decimal('price_difference', 12, 2)
                ->default(0);

            $table->string('tracking_number')->nullable();

            $table->string('carrier')->nullable();

            $table->timestamp('processing_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->text('failure_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchanges');
    }
};