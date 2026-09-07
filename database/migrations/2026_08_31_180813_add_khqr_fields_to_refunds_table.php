<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('khqr_receiver_number')
                ->nullable()
                ->after('refund_reference');

            $table->string('khqr_account_name')
                ->nullable()
                ->after('khqr_receiver');

            $table->string('khqr_transaction_id')
                ->nullable()
                ->unique()
                ->after('khqr_account_name');
            $table->string('khqr_qr_code')->nullable()->after('khqr_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropUnique(['khqr_transaction_id']);

            $table->dropColumn([
                'khqr_receiver_number',
                'khqr_account_name',
                'khqr_transaction_id',
                'khqr_qr_code'
            ]);
        });
    }
};

