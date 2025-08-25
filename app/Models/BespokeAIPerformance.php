<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BespokeAIPerformance extends Model
{
    use HasFactory;

    protected $table = 'bespoke_ai_performance';

    protected $fillable = [
        'model_id',
        'game_id',
        'session_id',
        'total_questions',
        'correct_answers',
        'total_score',
        'max_possible_score',
        'accuracy_percentage',
        'improvement_from_baseline'
    ];

    protected $casts = [
        'total_questions' => 'integer',
        'correct_answers' => 'integer',
        'total_score' => 'integer',
        'max_possible_score' => 'integer',
        'accuracy_percentage' => 'decimal:2',
        'improvement_from_baseline' => 'decimal:2'
    ];

    public function model()
    {
        return $this->belongsTo(BespokeAIModel::class, 'model_id');
    }

    public function game()
    {
        return $this->belongsTo(Games::class, 'game_id');
    }
}