<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->string('section')->nullable()->after('recipe_id');
        });

        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->string('section')->nullable()->after('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->dropColumn('section');
        });

        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
};
