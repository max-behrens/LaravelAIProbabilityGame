<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BespokeAITrainingData extends Model
{
    use HasFactory;

    protected $table = 'bespoke_ai_training_data';

    protected $fillable = [
        'model_id',
        'game_id',
        'question_id',
        'question_text',
        'correct_answer',
        'player_answer',
        'ai_answer',
        'score_achieved',
        'max_possible_score',
        'difficulty_id',
        'category_id',
        'context_data'
    ];

    protected $casts = [
        'context_data' => 'array',
        'score_achieved' => 'integer',
        'max_possible_score' => 'integer',
        'difficulty_id' => 'integer',
        'category_id' => 'integer'
    ];

    public function model()
    {
        return $this->belongsTo(BespokeAIModel::class, 'model_id');
    }

    public function game()
    {
        return $this->belongsTo(Games::class, 'game_id');
    }

    public function question()
    {
        return $this->belongsTo(GameQuestion::class, 'question_id');
    }
}