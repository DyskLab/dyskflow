<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theme Builder templates can be limited to some pages, like Elementor's display conditions:
 * a JSON list such as ["category:3", "page:12"]. Empty means everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('conditions')->nullable()->after('builder_draft');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('conditions');
        });
    }
};
