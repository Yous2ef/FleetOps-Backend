<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->bigIncrements('preference_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('email_enabled')->default(true);
            $table->string('quiet_hours_start', 5)->nullable();
            $table->string('quiet_hours_end', 5)->nullable();
            $table->string('preferred_language', 2)->default('en');
            $table->text('fcm_token')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
