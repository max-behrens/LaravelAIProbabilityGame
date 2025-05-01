<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\GameQuestion;

class GameType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function gameQuestions()
    {
        return $this->hasMany(GameQuestion::class, 'game_type_id');
    }
}
