<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_orders', function (Blueprint $table) {
            // Refund-specific columns
            if (Schema::hasColumn('return_orders', 'refund_amount')) {
                $table->dropColumn('refund_amount');
            }

            if (Schema::hasColumn('return_orders', 'refund_status')) {
                $table->dropColumn('refund_status');
            }

            if (Schema::hasColumn('return_orders', 'refund_method')) {
                $table->dropColumn('refund_method');
            }

            if (Schema::hasColumn('return_orders', 'refund_processing_at')) {
                $table->dropColumn('refund_processing_at');
            }

            if (Schema::hasColumn('return_orders', 'refunded_at')) {
                $table->dropColumn('refunded_at');
            }

            // Exchange-specific columns
            if (Schema::hasColumn('return_orders', 'exchange_processing_at')) {
                $table->dropColumn('exchange_processing_at');
            }

            if (Schema::hasColumn('return_orders', 'exchange_shipped_at')) {
                $table->dropColumn('exchange_shipped_at');
            }

            if (Schema::hasColumn('return_orders', 'exchange_received_at')) {
                $table->dropColumn('exchange_received_at');
            }

            if (Schema::hasColumn('return_orders', 'exchange_tracking_number')) {
                $table->dropColumn('exchange_tracking_number');
            }

            if (Schema::hasColumn('return_orders', 'exchange_carrier')) {
                $table->dropColumn('exchange_carrier');
            }
            $table->timestamp('inspected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('return_orders', function (Blueprint $table) {
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->string('refund_status')->nullable();
            $table->string('refund_method')->nullable();
            $table->timestamp('refund_processing_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamp('exchange_processing_at')->nullable();
            $table->timestamp('exchange_shipped_at')->nullable();
            $table->timestamp('exchange_received_at')->nullable();
            $table->string('exchange_tracking_number')->nullable();
            $table->string('exchange_carrier')->nullable();
            $table->dropColumn('inspected_at')->nullable();
        });
    }
};