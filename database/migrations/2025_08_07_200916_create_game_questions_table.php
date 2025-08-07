<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('game_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_type_id')->constrained('game_types');
            $table->foreignId('difficulty_id')->constrained('game_type_difficulties');
            $table->foreignId('category_id')->constrained('game_type_categories');
            $table->text('question');
            $table->string('answer');
            $table->integer('score_awarded');
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('game_questions');
    }
};