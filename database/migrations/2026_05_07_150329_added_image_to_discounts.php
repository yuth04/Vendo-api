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
        Schema::table('discounts', function (Blueprint $table) {
            $table->string('banner_image')->nullable();
            $table->string('image_file_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('slug')->unique()->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn([
                'banner_image',
                'image_file_id',
                'is_active',
                'slug'
            ]);

        });
    }
};
