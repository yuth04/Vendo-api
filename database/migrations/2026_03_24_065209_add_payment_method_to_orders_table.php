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
            $table->string('payment_method')->nullable()->after('status'); // e.g., 'bakong'
            $table->string('payment_status')->default('pending')->after('payment_method'); // 'pending', 'paid', 'failed'
            $table->string('payment_reference')->nullable()->unique()->after('payment_status'); // for Bakong transaction reference
            $table->string('contact')->nullable();
            $table->text('note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['address_id', 'payment_method', 'payment_status', 'payment_reference,', 'contact', 'note']);
        });
    }
};
