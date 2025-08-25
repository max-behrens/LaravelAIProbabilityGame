<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BespokeAIScore extends Model
{
    use HasFactory;

    protected $table = 'bespoke_ai_scores';

    protected $fillable = [
        'game_id',
        'model_id',
        'session_id',
        'score',
        'answer_json'
    ];

    protected $casts = [
        'answer_json' => 'array',
        'score' => 'integer'
    ];

    public function game()
    {
        return $this->belongsTo(Games::class, 'game_id');
    }

    public function model()
    {
        return $this->belongsTo(BespokeAIModel::class, 'model_id');
    }
}