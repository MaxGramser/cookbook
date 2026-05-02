<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cook_sessions', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('completed_at');
            $table->unsignedInteger('paused_seconds')->default(0)->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('cook_sessions', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'paused_seconds']);
        });
    }
};
