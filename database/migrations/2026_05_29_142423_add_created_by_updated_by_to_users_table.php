<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('created_by')->nullable()->after('image_file_id');
            $table->json('updated_by')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->unsignedBigInteger('updated_by')->nullable()->change();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }
};
