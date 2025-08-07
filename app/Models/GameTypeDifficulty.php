<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameTypeDifficulty extends Model
{
    use HasFactory;

    protected $table = 'game_type_difficulties';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function gameQuestions(): HasMany
    {
        return $this->hasMany(GameQuestion::class, 'difficulty_id');
    }
}