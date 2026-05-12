<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('incident_id')->nullable()->after('mechanic_id');
            $table->foreign('incident_id')->references('incident_id')->on('incident_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_assignments', function (Blueprint $table) {
            $table->dropForeign(['incident_id']);
            $table->dropColumn('incident_id');
        });
    }
};
