<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->enum('status', ['lobby', 'submitting', 'playing', 'grading', 'grading_complete', 'round_complete', 'complete'])
                ->default('lobby')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->enum('status', ['lobby', 'submitting', 'playing', 'grading', 'complete'])
                ->default('lobby')
                ->change();
        });
    }
};
