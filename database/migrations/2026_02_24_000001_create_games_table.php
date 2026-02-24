<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_user_id')->constrained('users');
            $table->string('code', 6)->unique();
            $table->enum('status', ['lobby', 'submitting', 'playing', 'grading', 'complete'])->default('lobby');
            $table->unsignedInteger('current_round')->default(1);
            $table->unsignedInteger('max_rounds')->default(1);
            $table->timestamp('state_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
