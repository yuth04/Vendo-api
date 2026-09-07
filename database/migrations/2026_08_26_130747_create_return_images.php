<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {

        if (Schema::hasTable('return_images')) {
            return;
        }

        Schema::create('return_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_order_id')->constrained('return_orders')->cascadeOnDelete();
            $table->string('url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_images');
    }
};