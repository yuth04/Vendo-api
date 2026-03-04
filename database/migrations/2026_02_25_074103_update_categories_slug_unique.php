<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            // drop the old unique index on slug
            $table->dropUnique('categories_slug_unique');

            // add composite unique key: parent_category_id + slug
            $table->unique(['parent_category_id', 'slug'], 'categories_parent_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // drop the composite unique key
            $table->dropUnique('categories_parent_slug_unique');

            // restore the old global unique key
            $table->unique('slug');
        });
    }
};