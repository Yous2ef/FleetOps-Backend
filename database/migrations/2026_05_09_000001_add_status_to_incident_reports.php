<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->enum('status', ['Open', 'Resolved', 'In Progress'])->default('Open')->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
