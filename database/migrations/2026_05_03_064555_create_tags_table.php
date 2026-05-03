<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32);
            $table->string('slug', 64);
            $table->string('name', 80);
            $table->string('color', 32)->default('cream');
            $table->unsignedSmallInteger('sort')->default(100);
            $table->boolean('is_system')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group', 'slug']);
            $table->index(['group', 'sort']);
            $table->index(['user_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
