<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->foreignId('replacement_variant_id')
                ->nullable()
                ->after('order_item_id')
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->unsignedInteger('exchange_quantity')
                ->nullable();

            $table->decimal('exchange_price_difference', 12, 2)
                ->nullable();
            $table->string('return_type')->default('refund')
                ->after('exchange_price_difference');
        });

    }

    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropForeign([
                'replacement_variant_id',
            ]);

            $table->dropColumn([
                'replacement_variant_id',
                'exchange_quantity',
                'exchange_price_difference',
                'return_type',
            ]);
        });
    }
};