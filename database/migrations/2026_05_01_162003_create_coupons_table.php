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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code')->unique();

            $table->enum('type', ['fixed', 'percent']);
            $table->decimal('value', 10, 2);

            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();

            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();

            $table->integer('usage_limit')->nullable();
            $table->integer('used')->default(0);

            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->string('image_file_id')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
