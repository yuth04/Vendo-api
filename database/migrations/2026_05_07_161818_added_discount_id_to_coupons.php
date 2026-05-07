<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('discount_id')
                ->nullable()
                ->constrained('discounts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {

            $table->dropForeign(['discount_id']);
            $table->dropColumn('discount_id');

    
        });
    }
};