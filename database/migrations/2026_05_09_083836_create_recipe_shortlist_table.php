<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_shortlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shortlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['shortlist_id', 'recipe_id']);
            $table->index(['shortlist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_shortlist');
    }
};
