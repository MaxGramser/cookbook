<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cook_session_ingredient_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cook_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_ingredient_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');

            $table->unique(['cook_session_id', 'recipe_ingredient_id'], 'cook_ing_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cook_session_ingredient_checks');
    }
};
