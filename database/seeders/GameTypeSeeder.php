<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameType;

class GameTypeSeeder extends Seeder
{
    public function run(): void
    {
        $gameTypes = [
            ['id' => 1, 'name' => 'Object Detection Game'],
            ['id' => 2, 'name' => 'Fake or Steal'],
        ];

        foreach ($gameTypes as $gameType) {
            GameType::updateOrCreate(
                ['id' => $gameType['id']],
                $gameType
            );
        }
    }
}