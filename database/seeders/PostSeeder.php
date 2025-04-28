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
        // Check if there are any posts in the database
        if (DB::table('posts')->count() > 0) {
            // If there are posts, skip the seeding and log it
            \Log::info('Posts table already contains data. Skipping post seeding.');
            return;
        }

        $limit = 5;  // Set the limit to 5 posts
        $chunk_size = 5;  // Insert the posts in chunks of 5
        $data = [];
        $users = collect(User::all()->modelKeys());

        for ($i = 0; $i < $limit; ++$i) {
            $slug = Str::slug(Str::random(rand(10, 50)));

            // Prepare data only for new posts
            $data[] = [
                'title' => Str::random(10),
                'content' => Str::random(50),
                'slug' => $slug,
                'user_id' => $users->random(),
                'is_active' => 1,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        // Insert posts in chunks
        foreach (array_chunk($data, $chunk_size) as $chunk) {
            DB::table('posts')->insert($chunk);
        }
    }
}
