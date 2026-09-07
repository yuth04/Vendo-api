<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            if (!Schema::hasColumn('return_items', 'condition')) {
                $table->string('condition')->nullable()->after('reason');
                // e.g. 'good', 'damaged' — set by admin at inspection/complete time
            }

            if (!Schema::hasColumn('return_items', 'restocked')) {
                $table->boolean('restocked')->default(false)->after('condition');
            }
        });
    }

    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropColumn(['condition', 'restocked']);
        });
    }
};