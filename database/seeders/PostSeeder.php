<?php


namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostSeeder extends Seeder
{
    public function run()
    {
        $limit = 5;  // Set the limit to 5
        $chunk_size = 5;  // Insert the posts in chunks of 5 (or adjust as needed)
        $data = [];
        $users = collect(User::all()->modelKeys());

        for ($i = 0; $i < $limit; ++$i) {
            // Using firstOrCreate to ensure posts are not duplicated
            $data[] = [
                'title' => Str::random(10),
                'content' => Str::random(50),
                'slug' => Str::slug(Str::random(rand(10, 50))),
                'user_id' => $users->random(),
                'is_active' => 1,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        // Chunk the data and insert it
        foreach ($data as $postData) {
            DB::table('posts')->updateOrInsert(
                ['slug' => $postData['slug']],  // Unique field to avoid duplication (slug)
                $postData
            );
        }
    }
}
