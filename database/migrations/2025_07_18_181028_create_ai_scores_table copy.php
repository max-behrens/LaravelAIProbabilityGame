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
            $table->unsignedBigInteger('game_id');
            $table->unsignedBigInteger('question_id');
            $table->string('session_id');
            $table->text('answer')->nullable();
            $table->integer('score')->default(0);
            $table->json('answer_json')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            // Assuming you have a game_questions table
            $table->foreign('question_id')->references('id')->on('game_questions')->onDelete('cascade');
            
            // Index for performance
            $table->index(['game_id', 'question_id']);
            $table->index('session_id');
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