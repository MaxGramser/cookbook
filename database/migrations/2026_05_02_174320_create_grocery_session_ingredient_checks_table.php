<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grocery_session_ingredient_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grocery_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_ingredient_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');

            $table->unique(['grocery_session_id', 'recipe_ingredient_id'], 'grocery_ing_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grocery_session_ingredient_checks');
    }
};
