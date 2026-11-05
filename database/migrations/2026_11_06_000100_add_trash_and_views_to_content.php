<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->softDeletes();
            $table->unsignedBigInteger('view_count')->default(0);
        });
        Schema::table('comments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('view_count');
        });
        Schema::table('comments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
