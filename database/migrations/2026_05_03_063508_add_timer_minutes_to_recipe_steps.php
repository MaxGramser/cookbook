<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->unsignedSmallInteger('timer_minutes')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->dropColumn('timer_minutes');
        });
    }
};
