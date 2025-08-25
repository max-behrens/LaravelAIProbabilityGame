<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Bespoke AI Models
        Schema::create('bespoke_ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('model_type', ['basic', 'adaptive', 'competitive'])->default('basic');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Bespoke AI Scores
        Schema::create('bespoke_ai_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('model_id')->constrained('bespoke_ai_models')->cascadeOnDelete();
            $table->string('session_id');
            $table->integer('score')->default(0);
            $table->json('answer_json');
            $table->timestamps();

            $table->index(['game_id', 'session_id'], 'idx_game_session');
            $table->index('model_id', 'idx_model_id');
        });

        // Bespoke AI Training Data
        Schema::create('bespoke_ai_training_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('bespoke_ai_models')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedBigInteger('question_id');
            $table->text('question_text');
            $table->text('correct_answer');
            $table->text('player_answer')->nullable();
            $table->text('ai_answer');
            $table->integer('score_achieved');
            $table->integer('max_possible_score');
            $table->integer('difficulty_id')->nullable();
            $table->integer('category_id')->nullable();
            $table->json('context_data')->nullable();
            $table->timestamps();

            $table->index(['model_id', 'created_at'], 'idx_model_learning');
            $table->index(['question_id', 'difficulty_id', 'category_id'], 'idx_question_context');
        });

        // Bespoke AI Performance
        Schema::create('bespoke_ai_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('bespoke_ai_models')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('session_id');
            $table->integer('total_questions');
            $table->integer('correct_answers');
            $table->integer('total_score');
            $table->integer('max_possible_score');
            $table->decimal('accuracy_percentage', 5, 2);
            $table->decimal('improvement_from_baseline', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['model_id', 'created_at'], 'idx_performance_tracking');
            $table->unique(['model_id', 'session_id'], 'unique_session_model');
        });

        // Seed default models
        DB::table('bespoke_ai_models')->insert([
            [
                'name' => 'Basic Learner',
                'description' => 'Simple learning model that adapts based on correct answers',
                'model_type' => 'basic',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Adaptive AI',
                'description' => 'Advanced model that learns from player patterns and question contexts',
                'model_type' => 'adaptive',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Competitive AI',
                'description' => 'Elite model designed to maximize scores through strategic learning',
                'model_type' => 'competitive',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bespoke_ai_performance');
        Schema::dropIfExists('bespoke_ai_training_data');
        Schema::dropIfExists('bespoke_ai_scores');
        Schema::dropIfExists('bespoke_ai_models');
    }
};
