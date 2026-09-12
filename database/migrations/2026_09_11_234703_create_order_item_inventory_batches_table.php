<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_inventory_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            $table->foreignId('inventory_batch_id')
                ->constrained('inventory_batches')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            $table->decimal('unit_cost', 12, 2)->nullable();

            $table->timestamps();

            $table->index([
                'order_item_id',
                'inventory_batch_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_inventory_batches');
    }
};