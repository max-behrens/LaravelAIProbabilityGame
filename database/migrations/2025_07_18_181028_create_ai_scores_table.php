<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_scores', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->foreignId('game_id')->constrained('games');
            $table->integer('score');
            $table->json('answer_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_scores');
    }
};