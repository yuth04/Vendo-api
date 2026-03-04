<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('productName')->nullable();
            $table->string('slug')->nullable();
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2);
            // $table->integer("stock")->default(0);
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('brand_id')->nullable();
             $table->string('image')->nullable();
            // foriegn key
            $table->foreign("category_id")->references("id")->on("categories")->onDelete("cascade");
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');

    }
};
