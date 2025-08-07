<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameQuestion extends Model
{
    use HasFactory;

    protected $table = 'game_questions';

    protected $fillable = [
        'game_type_id',
        'difficulty_id',
        'category_id',
        'question',
        'answer',
        'score_awarded',
    ];

    public function gameType(): BelongsTo
    {
        return $this->belongsTo(GameType::class, 'game_type_id');
    }

    public function difficulty(): BelongsTo
    {
        return $this->belongsTo(GameTypeDifficulty::class, 'difficulty_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GameTypeCategory::class, 'category_id');
    }
}