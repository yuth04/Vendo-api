<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('return_item_id')
                ->nullable()
                ->after('return_order_id')
                ->constrained('return_items')
                ->nullOnDelete();
        });

        Schema::table('exchanges', function (Blueprint $table) {
            $table->foreignId('return_item_id')
                ->nullable()
                ->after('return_order_id')
                ->constrained('return_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['return_item_id']);
            $table->dropColumn('return_item_id');
        });

        Schema::table('exchanges', function (Blueprint $table) {
            $table->dropForeign(['return_item_id']);
            $table->dropColumn('return_item_id');
        });
    }
};