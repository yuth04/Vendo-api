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
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('alt_text')->nullable()->after('image');
            $table->boolean('is_primary')->default(false)->after('position');
            $table->string('image_file_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('alt_text')->nullable()->after('image');
            $table->dropColumn('is_primary')->default(false)->after('position');
            $table->dropColumn('image_file_id');
            });
    }
};
