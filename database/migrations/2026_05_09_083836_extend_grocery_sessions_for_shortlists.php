<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grocery_sessions', function (Blueprint $table) {
            $table->foreignId('recipe_id')->nullable()->change();
            $table->foreignId('shortlist_id')
                ->nullable()
                ->after('recipe_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->index('shortlist_id');
        });
    }

    public function down(): void
    {
        Schema::table('grocery_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shortlist_id');
            $table->foreignId('recipe_id')->nullable(false)->change();
        });
    }
};
