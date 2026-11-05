<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('title');
            $table->json('params')->nullable();
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->string('capability', 60)->nullable()->index();
            $table->string('fingerprint', 64)->nullable()->unique();
            $table->unsignedInteger('count')->default(1);
            $table->timestamps();
            $table->index('updated_at');
        });

        // No foreign key to users: restoring a backup replaces that table, and notifications are not part of backups.
        Schema::create('admin_notification_reads', function (Blueprint $table) {
            $table->foreignId('notification_id')->constrained('admin_notifications')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->primary(['notification_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_reads');
        Schema::dropIfExists('admin_notifications');
    }
};
