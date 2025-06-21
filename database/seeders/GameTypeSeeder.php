<?php

namespace Database\Seeders;

use App\Models\GameType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GameTypeSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        foreach (GameType::DEFAULT_TYPES as $index => $name) {
            DB::table('game_types')->updateOrInsert(
                ['id' => $index + 1],
                [
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
