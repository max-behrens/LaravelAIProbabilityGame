<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Post;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Services\Dashboard\PostService;
use App\Http\Requests\UpdatePostRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{

    public function __construct()
    {
        $this->authorizeResource(Post::class, 'post');
    }

    public function index(Request $request, PostService $service)
    {
        $request->validate([
            'field' => ['nullable', 'in:id,title,content,created_at,username,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'search' => ['nullable'],
        ]);
    
        $data = $service->datatable($request);
        $disk = config('filesystems.default');
    
        // Log featured image URLs before assigning them
        $data['posts']->getCollection()->transform(function ($post) use ($disk) {
            $imagePath = $post->featured_image
                ? Storage::disk($disk)->url("posts/{$post->id}/{$post->featured_image}")
                : null;
    
            Log::info('Generated image path for post', [
                'post_id' => $post->id,
                'filename' => $post->featured_image,
                'full_s3_url' => $imagePath,
            ]);
    
            return $post;
        });
    
        return Inertia::render('Dashboard/Posts/Index', $data);
    }
    


    public function create(Request $request, PostService $service)
    {

        $chatbotMessages = json_decode($request->query('chatbotMessages', '[]'), true);

        // Extract dialogue data
        $dialogueData = [
            'calculationResults' => $service->formatCalculationResults($request->query('calculationResults', '{}')),
            'aiResponseResults' => $service->formatAiResponseResults($request->query('aiResponseResults', '{}')),
            'chatbotMessages' => $service->formatChatbotMessages(json_decode($request->query('chatbotMessages', '[]'), true)),
        ];

        Log::info('dialogueData: ', [
            'dialogueData' => $dialogueData
        ]);

        // Return Inertia response
        return Inertia::render('Dashboard/Posts/Create', [
            'dialogueData' => $dialogueData,
        ]);
    }


    public function store(StorePostRequest $request, PostService $service)
    {
        // Prepare the data for the new post, excluding the 'featured_image'
        $postData = $request->safe()->toArray();
    
        Log::info('REQUEST ADD POST ', ['$request' => $request]);
    
        // Create the post in the database (without the featured image)
        $post = Post::create($postData);
    
        // Store the featured image (if uploaded)
        $path = $service->storeFeaturedImage($post, $request);

        Log::info('POST IMAGE PATH', ['$path' => $path]);
    
        // Update the post with the featured image URL
        $service->updatePostFeaturedImage($post, $path);
    
        // Return a response or redirect to another page
        return redirect()->route('posts.index')->with('message', 'Post Created Successfully');
    }
    




    public function edit(Post $post)
    {
        $disk = config('filesystems.default');
    
        // $featuredImageUrl = $post->featured_image
        //     ? Storage::disk($disk)->url($post->featured_image)
        //     : null;
    
        return Inertia::render('Dashboard/Posts/Edit', [
            'post' => [
                ...$post->toArray(),
                'featured_image' => $post->featured_image,
            ]
        ]);
    }
    

    public function update(Post $post, UpdatePostRequest $request, PostService $service)
    {

        Log::info('REQUEST UPDATE POST ', ['$request' => $request]);

        $post->update($request->safe()->toArray());

        $path = $service->storeFeaturedImage($post, $request);

        $service->updatePostFeaturedImage($post, $path);

        return redirect()->route('posts.index')->with('message', 'Post Updated Successfully');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()->route('posts.index')->with('message', 'Post Delete Successfully');
    }

    public function publish(Post $post, Request $request)
    {
        if ($request->user()->cannot('publish', $post)) {
            abort(404);
        }

        $request->validate([
            'is_active' => 'required|integer|in:0,1',
        ]);

        $post->update([
            'is_active' => $request->is_active
        ]);

        return redirect()->route('posts.index')->with('message', sprintf('Post %s Successfully', $request->is_active ? 'Published' : 'Unpublished'));
    }

}
