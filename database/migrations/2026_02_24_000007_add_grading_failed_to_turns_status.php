<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->enum('status', ['pending', 'choosing', 'recording', 'grading', 'complete', 'grading_failed'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->enum('status', ['pending', 'choosing', 'recording', 'grading', 'complete'])
                ->default('pending')
                ->change();
        });
    }
};
