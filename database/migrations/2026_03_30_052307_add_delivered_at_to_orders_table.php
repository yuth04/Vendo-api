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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('shipped_at')->nullable()->after('status');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at'); // optional but recommended
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            $table->timestamp('returned_at')->nullable()->after('cancelled_at');
       
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipped_at',
                'delivered_at',
                'cancelled_at',
                'returned_at'
            ]);
        });
    }
};
