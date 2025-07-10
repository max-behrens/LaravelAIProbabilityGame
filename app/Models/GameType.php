<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\GameQuestion;

class GameType extends Model
{
    use HasFactory;

    public const DEFAULT_TYPES = [
        'The Number Game',
        'The Word Game',
        'The Geography Game',
        'The History Game',
        'The Science Game',
    ];

    protected $fillable = [
        'name',
    ];

    public function games()
    {
        return $this->hasMany(Games::class, 'game_type_id');
    }

    public function gameQuestions()
    {
        return $this->hasMany(GameQuestion::class, 'game_type_id');
    }
}
