<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->timestamp('starred_at')->nullable()->after('notes');
            $table->timestamp('last_cooked_at')->nullable()->after('starred_at');
            $table->unsignedInteger('cooked_count')->default(0)->after('last_cooked_at');

            $table->index('starred_at');
            $table->index('last_cooked_at');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['starred_at']);
            $table->dropIndex(['last_cooked_at']);
            $table->dropColumn(['starred_at', 'last_cooked_at', 'cooked_count']);
        });
    }
};
