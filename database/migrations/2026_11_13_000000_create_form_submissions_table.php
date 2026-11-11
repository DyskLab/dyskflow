<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messages sent with the builder's Form widget (Content > Forms). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('form', 190)->index();
            $table->string('email', 190)->nullable();
            $table->text('data');
            $table->string('page_url', 500)->nullable();
            $table->string('page_title', 255)->nullable();
            $table->string('locale', 5)->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
