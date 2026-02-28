<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->decimal('score', 5, 1)->nullable()->change();
        });

        Schema::table('players', function (Blueprint $table) {
            $table->decimal('score', 6, 1)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->unsignedSmallInteger('score')->nullable()->change();
        });

        Schema::table('players', function (Blueprint $table) {
            $table->integer('score')->default(0)->change();
        });
    }
};
