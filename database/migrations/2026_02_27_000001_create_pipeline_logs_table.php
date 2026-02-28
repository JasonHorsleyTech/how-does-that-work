<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('audio_received');

            // Audio receipt
            $table->timestamp('audio_received_at')->nullable();
            $table->string('audio_file_path')->nullable();
            $table->unsignedBigInteger('audio_file_size_bytes')->nullable();

            // Whisper transcription
            $table->timestamp('whisper_sent_at')->nullable();
            $table->timestamp('whisper_response_at')->nullable();
            $table->text('whisper_transcript')->nullable();
            $table->text('whisper_error')->nullable();

            // GPT grading
            $table->timestamp('gpt_sent_at')->nullable();
            $table->timestamp('gpt_response_at')->nullable();
            $table->text('gpt_response_raw')->nullable();
            $table->text('gpt_error')->nullable();

            // General
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_logs');
    }
};
