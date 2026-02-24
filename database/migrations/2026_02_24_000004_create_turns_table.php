<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games');
            $table->foreignId('player_id')->constrained('players');
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->unsignedInteger('round_number');
            $table->unsignedInteger('turn_order');
            $table->enum('status', ['pending', 'choosing', 'recording', 'grading', 'complete'])->default('pending');
            $table->string('audio_path')->nullable();
            $table->text('transcript')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->string('grade', 2)->nullable();
            $table->text('feedback')->nullable();
            $table->text('actual_explanation')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turns');
    }
};
