<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_orders', function (Blueprint $table) {
            // Not present on the existing table at all — the controller's
            // store()/index() read and write $return->image as the legacy
            // single-image field (ReturnImage rows are used for the full
            // gallery on top of this).
            if (!Schema::hasColumn('return_orders', 'image')) {
                $table->string('image')->nullable()->after('note');
            }

            if (!Schema::hasColumn('return_orders', 'refund_method')) {
                $table->string('refund_method')->nullable()->after('refund_amount');
                // e.g. 'original_payment', 'store_credit'
            }

            // refund_status already exists on your table (default 'pending') —
            // intentionally not touched here.

            if (!Schema::hasColumn('return_orders', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('note');
            }

            if (!Schema::hasColumn('return_orders', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('return_orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('received_at');
            }

            if (!Schema::hasColumn('return_orders', 'carrier')) {
                $table->string('carrier')->nullable()->after('tracking_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('return_orders', function (Blueprint $table) {
            $table->dropColumn([
                'image',
                'refund_method',
                'admin_note',
                'received_at',
                'tracking_number',
                'carrier',
            ]);
        });
    }
};