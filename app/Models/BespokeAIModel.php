<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BespokeAIModel extends Model
{
    use HasFactory;

    protected $table = 'bespoke_ai_models';

    protected $fillable = [
        'name',
        'description',
        'model_type',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function scores()
    {
        return $this->hasMany(BespokeAIScore::class, 'model_id');
    }

    public function trainingData()
    {
        return $this->hasMany(BespokeAITrainingData::class, 'model_id');
    }

    public function performance()
    {
        return $this->hasMany(BespokeAIPerformance::class, 'model_id');
    }

    public static function getActiveModels()
    {
        return self::where('is_active', true)->get();
    }
}