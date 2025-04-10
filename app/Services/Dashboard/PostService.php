<?php

namespace App\Services\Dashboard;

use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class PostService
{
    public function datatable(Request $request): array
    {
        // Retrieve search, sort, and pagination parameters from the request
        $search = $request->filled('search') ? $request->search : NULL;
        $sort_field = $request->filled('field') ? $request->field : 'created_at';
        $sort_direction = $request->filled('direction') ? $request->direction : 'desc';
        $page = $request->input('page', 1);  // Default to the first page if not provided
        $perPage = $request->input('per_page', 3);  // Default to 10 posts per page if not provided
    
        Log::info('search:', ['search' => $search]);
    
        // Begin building the query
        $query = Post::query()
            ->select([
                'posts.id',
                'posts.user_id',
                'posts.title',
                'posts.slug',
                'posts.content',
                'posts.is_active',
                'posts.featured_image',
                'posts.created_at',
                'posts.updated_at',
                'users.id as userid',
                'users.name as username',
            ])
            ->join('users', 'users.id', '=', 'posts.user_id');
    
        // Apply search filters
        if ($search) {
            $query->when($search, function ($query) use ($search) {
                $query->search('title', $search);
                $query->orSearch('slug', $search);
                $query->orSearch('content', $search);
            });
        }
    
        // Apply sorting
        if ($sort_field && $sort_direction) {
            $query->orderBy($sort_field, $sort_direction);
        }
    
        // Pagination logic
        $posts = $query->paginate($perPage, ['*'], 'page', $page)
            ->through(function ($post) {
                // Add permissions and content formatting
                $post->permissions = [
                    'create' => Auth::user()->can('create', Post::class),
                    'edit' => Auth::user()->can('update', $post),
                    'delete' => Auth::user()->can('delete', $post),
                    'publish' => Auth::user()->can('publish', $post),
                    'unpublish' => Auth::user()->can('unpublish', $post),
                ];
    
                // Truncate content and title for display
                $post->content_limited = Str::of($post->content)->limit(300);
                $post->title_limited = Str::of($post->title)->limit(15);
                $post->username_limited = Str::of($post->username)->limit(15);
    
                return $post;
            })
            ->withQueryString();  // Keeps the query parameters for pagination in the URL
        
        return [
            'posts' => $posts,  // Return paginated posts with pagination data
            'filters' => [
                'search' => $search,
                'field' => $sort_field,
                'direction' => $sort_direction,
            ],
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],  // Return pagination data to be used in frontend
        ];
    }
    
    
    public function storeFeaturedImage(Post $post, Request $request): string
    {
        if ($request->hasFile('featured_image') && $request->file('featured_image')->isValid()) {
            $this->removePreviousFeaturedImage($post);

            $isLocal = app()->environment('local');
    
            $file = $request->file('featured_image');
            Log::info('File:', ['file' => $file]);
            $filename = $file->getClientOriginalName();
            Log::info('Filename:', ['filename' => $filename]);
            $path = "posts/{$post->id}/{$filename}";
            Log::info('Path:', ['path' => $path]);
    
            // Use configured disk
            $disk = $isLocal ? 'public' : config('filesystems.default');

            Log::info('Uploading featured image', [
                'environment' => app()->environment(),
                'disk' => $disk,
                'path' => $path,
                'filename' => $filename,
                'file_exists' => $file->isValid(),
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType()
            ]);

                // Actually store the file
                $filename = Str::random(40) . '.' . $file->getClientOriginalExtension(); // make it unique manually


                $path = "posts/{$post->id}/{$filename}";

                Log::info('Storing file', [
                    'path' => $path,
                    'filename' => $filename,
                    'disk' => $disk,
                    'isLocal' => $isLocal,
                ]);

                if ($isLocal) {
                    Storage::disk($disk)->put($path, file_get_contents($file), 'public');
                } else {
                    Storage::disk($disk)->put($path, file_get_contents($file), $filename, 'public');
                }

                // Then store just $filename in DB:
                $post->featured_image = $filename;

                Log::info('post->featured_image:', ['post->featured_image' => $post->featured_image]);
                $post->save();

    
                Log::info('Successfully uploaded image', [
                    'disk' => $disk, 
                    'stored_path' => $path,
                    'public_url' => Storage::disk($disk)->url($path)
                ]);


                if ($isLocal) {
                    return asset('storage/' . $path); // Local URL for local environment
                } else {
                    // ✅ Return the public URL for S3 (or just the stored path if you prefer)
                    return Storage::disk($disk)->url($path);
                }

    
        }

    
        return $post->featured_image ?? '';
    }
    


    public function updatePostFeaturedImage(Post $post, string $filename): void
    {
        if ($filename) {
            $post->update([
                'featured_image' => $filename
            ]);
        }
    }
    
    
    public function removePreviousFeaturedImage(Post $post): void
    {
        if ($post->featured_image) {
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $path = "posts/{$post->id}/{$post->featured_image}";
            
            try {
                Storage::disk($disk)->delete($path);
                Log::info("Deleted previous featured image", ['path' => $path, 'disk' => $disk]);
            } catch (\Exception $e) {
                Log::error("Failed to delete previous featured image", [
                    'path' => $path, 
                    'disk' => $disk,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    



    public function formatCalculationResults(?string $calculationResults = null): string
    {
        if (empty($calculationResults)) {
            return '';
        }

        $decodedCalculations = json_decode($calculationResults, true);
        if (!is_array($decodedCalculations)) {
            return '';
        }

        $formattedCalculations = [];

        foreach ($decodedCalculations as $key => $value) {
            $formattedKey = Str::headline($key);

            // Convert arrays to readable string
            $formattedValue = is_array($value) ? implode(", ", $value) : $value;

            $formattedCalculations[] = "{$formattedKey}: {$formattedValue}";
        }


        Log::info('formattedCalculations:', ['formattedCalculations' => $formattedCalculations]);


        return implode("\n\n", $formattedCalculations);
    }


    public function formatAiResponseResults(?string $aiResponseResults): string
    {

        $contentSections = [];

        // Format AI Response Results
        if (!empty($aiResponseResults)) {
            $decodedResults = json_decode($aiResponseResults, true);

            if (is_array($decodedResults)) {
                $formattedResults = [];
                foreach ($decodedResults as $key => $value) {
                    // Convert camelCase keys to readable headers
                    $formattedKey = Str::headline(str_replace('Explanation', '', $key));
                    $formattedResults[] = "{$formattedKey}: {$value}";
                }
                $contentSections[] = implode("\n\n", $formattedResults);
            }
        }

        Log::info('contentSections:', ['contentSections' => $contentSections]);


        return !empty($contentSections) ? implode("\n\n", $contentSections) : '';
    }

    public function formatChatbotMessages(?array $chatbotMessages): string
    {

         // Format Chatbot Messages
         if (!empty($chatbotMessages) && is_array($chatbotMessages)) {
            $formattedMessages = [];

            foreach ($chatbotMessages as $message) {
                if (!empty($message['text']) && isset($message['isUser'])) {
                    $prefix = $message['isUser'] ? "User:" : "AI:";
                    $formattedMessages[] = "{$prefix} {$message['text']}";
                }
            }

            if (!empty($formattedMessages)) {
                $contentSections[] = implode("\n\n", $formattedMessages);
            }
        }




        return !empty($contentSections) ? implode("\n\n", $contentSections) : '';
    }
}
