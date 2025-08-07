<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameTypeCategory;

class GameTypeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'Number', 'slug' => 'number'],
            ['id' => 2, 'name' => 'History', 'slug' => 'history'],
            ['id' => 3, 'name' => 'Geography', 'slug' => 'geography'],
        ];

        foreach ($categories as $category) {
            GameTypeCategory::updateOrCreate(
                ['id' => $category['id']],
                $category
            );
        }
    }
}