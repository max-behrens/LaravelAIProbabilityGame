<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        // Allow all authenticated users to view the posts index
        // Individual actions will be controlled by other policy methods
        return Response::allow();
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Post $post)
    {
        // Admin can view any post
        if ($user->hasRole('adminUser')) {
            return Response::allow();
        }

        // TestUser can view any post
        if ($user->hasRole('testUser')) {
            return Response::allow();
        }

        // Readonly users (users with 'user' role) cannot view any posts
        if ($user->hasRole('user')) {
            return Response::deny('You do not have permission to view this post.');
        }

        // Fallback: deny access
        return Response::deny('You do not have permission to perform this action.');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->hasPermissionTo('create posts')
            ? Response::allow()
            : Response::deny('You do not have permission to perform this action.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Post $post)
    {
        // Admin can edit any post
        if ($user->hasRole('adminUser')) {
            return $user->hasPermissionTo('edit posts')
                ? Response::allow()
                : Response::deny('You do not have permission to edit posts.');
        }

        // TestUser can edit any post
        if ($user->hasRole('testUser')) {
            return $user->hasPermissionTo('edit posts')
                ? Response::allow()
                : Response::deny('You do not have permission to edit posts.');
        }

        // Readonly users can only edit their own posts
        return $user->hasPermissionTo('edit posts') && $user->id === $post->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to edit this post.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Post $post)
    {
        // Admin can delete any post
        if ($user->hasRole('adminUser')) {
            return $user->hasPermissionTo('delete posts')
                ? Response::allow()
                : Response::deny('You do not have permission to delete posts.');
        }

        // TestUser can delete any post
        if ($user->hasRole('testUser')) {
            return $user->hasPermissionTo('delete posts')
                ? Response::allow()
                : Response::deny('You do not have permission to delete posts.');
        }

        // Regular users can only delete their own posts
        return $user->hasPermissionTo('delete posts') && $user->id === $post->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to delete this post.');
    }

    /**
     * Determine whether the user can publish the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function publish(User $user, Post $post)
    {
        // Admin can publish any post
        if ($user->hasRole('adminUser')) {
            return $user->hasPermissionTo('publish posts')
                ? Response::allow()
                : Response::deny('You do not have permission to publish posts.');
        }

        // TestUser can publish any post
        if ($user->hasRole('testUser')) {
            return $user->hasPermissionTo('publish posts')
                ? Response::allow()
                : Response::deny('You do not have permission to publish posts.');
        }

        // Regular users can only publish their own posts
        return $user->hasPermissionTo('publish posts') && $user->id === $post->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to publish this post.');
    }

    /**
     * Determine whether the user can unpublish the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function unpublish(User $user, Post $post)
    {
        // Admin can unpublish any post
        if ($user->hasRole('adminUser')) {
            return $user->hasPermissionTo('unpublish posts')
                ? Response::allow()
                : Response::deny('You do not have permission to unpublish posts.');
        }

        // TestUser can unpublish any post
        if ($user->hasRole('testUser')) {
            return $user->hasPermissionTo('unpublish posts')
                ? Response::allow()
                : Response::deny('You do not have permission to unpublish posts.');
        }

        // Regular users can only unpublish their own posts
        return $user->hasPermissionTo('unpublish posts') && $user->id === $post->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to unpublish this post.');
    }
}