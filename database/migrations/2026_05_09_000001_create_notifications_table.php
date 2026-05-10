<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('notification_id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('channel', 50);
            $table->string('event_type', 100);
            $table->json('payload')->nullable();
            $table->string('status', 50)->default('pending')->index();
            $table->string('dedup_key')->nullable()->index();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
