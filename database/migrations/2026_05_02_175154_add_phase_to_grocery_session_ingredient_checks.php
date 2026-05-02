<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grocery_session_ingredient_checks', function (Blueprint $table) {
            $table->enum('checked_in_phase', ['home', 'shopping'])->default('home')->after('recipe_ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::table('grocery_session_ingredient_checks', function (Blueprint $table) {
            $table->dropColumn('checked_in_phase');
        });
    }
};
