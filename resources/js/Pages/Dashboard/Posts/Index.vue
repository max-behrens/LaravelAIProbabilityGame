<script setup>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated.vue";
import PostLogo from '@/Components/PostLogo.vue';
import { Head } from "@inertiajs/inertia-vue3";
import BreezeButton from "@/Components/Button.vue";
import Pagination from "@/Components/Pagination.vue";
import SortArrowUp from "@/Components/SortArrowUp.vue";
import SortArrowDown from "@/Components/SortArrowDown.vue";
import { Link } from "@inertiajs/inertia-vue3";
import { Inertia } from "@inertiajs/inertia";
import { useForm } from "@inertiajs/inertia-vue3";
import { ref, watch, reactive, onMounted, computed } from 'vue';

const props = defineProps({
    posts: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    }
});

const form = useForm();

const params = reactive({
    search: props.filters.search,
    field: props.filters.field,
    direction: props.filters.direction,
    page: props.posts.current_page || 1,  // Track the current page
});

watch(params, () => {
    // let p = params;

    // Object.keys(p).forEach(key => {
    //     if (p[key] == '') {
    //         delete p[key];
    //     }
    // });

    // Inertia.get(route('posts.index'), p, { preserveState: true, preserveScroll: true })
});

function sort(field) {
    params.field = field;
    params.direction = params.direction === 'asc' ? 'desc' : 'asc';
}

function destroy(id) {
    if (confirm("Are you sure you want to Delete")) {
        form.delete(route("posts.destroy", id));
    }
}

function determineSortDirection(field, direction) {
    return params.field === field && params.direction === direction;
}

function publish(post, is_active) {
    Inertia.put(route("posts.publish", post), {
        is_active: is_active
    }, {
        preserveState: true,
        preserveScroll: true
    });
}

function setSearchInput(input, event) {
    params.search = input;

    if (event.key === 'Enter') {
        searchPosts(); // Trigger the search function when Enter is pressed
    }
}


function searchPosts() {
    // Handle the search request to make it trigger the API call
    Inertia.get(route('posts.index'), {
        search: params.search,
        field: params.field,
        direction: params.direction
    }, {
        preserveState: true,
        preserveScroll: true
    });
}

// Function to return the correct image URL for each post
const getPostImage = (post) => {
    const isProduction = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';

    const bucket = import.meta.env.VITE_AWS_BUCKET;
    const appUrl = import.meta.env.VITE_APP_URL;

    const fallbackImage = isProduction && bucket
        ? `https://${bucket}.s3.amazonaws.com/images/example-image.png`
        : '/images/example-image.png';

    if (!post.featured_image) {
        return fallbackImage;
    }

    console.log('isProduction', isProduction);
    console.log('bucket', bucket);
    console.log('post.featured_image', post.featured_image);
    console.log('post.id', post.id);
    
    let imageUrl = post.featured_image;

    if (isProduction && bucket) {

        console.log('Production environment detected. Using S3 URL.');

        imageUrl = imageUrl.replace(`.s3.eu-west-2.amazonaws.com`, `.s3.amazonaws.com`);

        console.log('imageUrl', imageUrl);
        // Build S3 URL manually
        return imageUrl;
    }

    // Local URL
    return `/storage/posts/${post.id}/${post.featured_image}`;
};
 









</script>

<template>

    <Head title="Posts" />
    <BreezeAuthenticatedLayout> <template #header>
            <h2 class="text-md font-semibold leading-tight text-white"> User Posts </h2>
        </template>
        <div class="py-12">
            <div class="mx-auto max-w-9xl sm:px-6 lg:px-8">
                <div v-if="$page.props.flash.message" class="alert alert-success shadow-lg mb-5">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $page.props.flash.message }}</span>
                    </div>
                </div>
                <div class="shadow-sm sm:rounded-lg max-w-7xl mx-auto overflow-x-hidden">
                    <div class="p-5 border-b border-gray-200">
                        <div class="mb-2">
                            <Link class="btn btn-accent" :href="route('posts.create')">Add Post</Link>
                        </div>
                        <div class="relative">
                            <div class="mt-6 mb-6">
                                <label for="default-search"
                                    class="mb-2 text-sm font-medium text-gray-900 sr-only dark:text-gray-300">Search</label>
                                <input id="default-search" type="text" v-debounce:300="setSearchInput"
                                    @keydown="setSearchInput($event.target.value, $event)" 
                                    class="input w-full max-w-xs placeholder-white text-white" placeholder="Search...">
                            </div>
                            <div class="block md:block">


                                <!-- Mobile view -->
                                <div class="block md:hidden">
                                    <div v-for="post in posts.data" :key="post.id" class="mb-4 rounded-lg shadow p-4">
                                        <div class="grid grid-cols-1 gap-2 break-words">
                                            <!-- Image -->
                                            <div class="flex justify-center">
                                                <div class="avatar">
                                                    <div class="w-22 rounded">
                                                        <img :src="getPostImage(post)" class="w-22 rounded" />
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Details -->
                                            <div class="grid grid-cols-2 gap-1">
                                                <div class="font-semibold">ID:</div>
                                                <div>{{ post.id }}</div>
                                                
                                                <div class="font-semibold">Title:</div>
                                                <div>{{ post.title_limited }}</div>
                                                
                                                <div class="font-semibold">Username:</div>
                                                <div>{{ post.username_limited }}</div>
                                                
                                                <div class="font-semibold">Content:</div>
                                                <div>{{ post.content_limited }}</div>
                                            </div>

                                            <!-- Actions -->
                                            <div class="flex justify-between items-center mt-2">
                                                <div v-if="post.permissions.publish && post.permissions.unpublish">
                                                    <input @change="publish(post.id, post.is_active)" 
                                                        type="checkbox"
                                                        true-value="1" 
                                                        false-value="0" 
                                                        v-model="post.is_active"
                                                        class="checkbox checkbox-md checkbox-accent" />
                                                </div>
                                                <div class="flex gap-2">
                                                    <Link v-if="post.permissions.edit" 
                                                        :href="route('posts.edit', post.id)" 
                                                        class="btn btn-warning btn-sm">Edit</Link>
                                                    <button v-if="post.permissions.delete" 
                                                            @click="destroy(post.id)"
                                                            class="btn btn-error btn-sm">Delete</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>



                                <!-- Desktop view -->
                                <div class="hidden md:block">
                                    <table class="table table-compact w-full text-center table-zebra border-collapse">
                                        <thead
                                            class=" text-xs uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 ">
                                            <tr>
                                                <th class="w-1/12 sticky top-0 ">Image</th>
                                                <th class="w-1/12" scope="col" @click="sort('id')">
                                                    <span class="inline-flex px-6 py-3 w-full justify-center ">
                                                        #
                                                        <SortArrowUp v-if="determineSortDirection('id', 'asc')">
                                                        </SortArrowUp>
                                                        <SortArrowDown v-if="determineSortDirection('id', 'desc')">
                                                        </SortArrowDown>
                                                    </span>
                                                </th>
                                                <th class="w-1/12"  scope="col" @click="sort('title')">
                                                    <span class="inline-flex px-6 py-3 w-full justify-center ">
                                                        Title
                                                        <SortArrowUp v-if="determineSortDirection('title', 'asc')">
                                                        </SortArrowUp>
                                                        <SortArrowDown v-if="determineSortDirection('title', 'desc')">
                                                        </SortArrowDown>
                                                    </span>
                                                </th>
                                                <th class="w-1/12"  scope="col" @click="sort('username')">
                                                    <span class="inline-flex px-6 py-3 w-full justify-center ">
                                                        Username
                                                        <SortArrowUp v-if="determineSortDirection('username', 'asc')">
                                                        </SortArrowUp>
                                                        <SortArrowDown v-if="determineSortDirection('username', 'desc')">
                                                        </SortArrowDown>
                                                    </span>
                                                </th>
                                                <th class="w-4/12"  scope="col" @click="sort('content')">
                                                    <span class="inline-flex px-6 py-3 w-full justify-center  items-center text-center">
                                                        Content
                                                        <SortArrowUp v-if="determineSortDirection('content', 'asc')">
                                                        </SortArrowUp>
                                                        <SortArrowDown v-if="determineSortDirection('content', 'desc')">
                                                        </SortArrowDown>
                                                    </span>
                                                </th>
                                                <th class="w-1/12"  scope="col">Publish</th>
                                                <th class="w-1/12"  scope="col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="post in posts.data" :key="post.id"
                                                class=" bg-white border-b dark:bg-gray-800 ">
                                                <td scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium text-gray-900 dark:text-white whitespace-nowrap whitespace-normal break-words">
                                                    <div class="avatar">
                                                        <div class="w-22 rounded">
                                                            <img :src="getPostImage(post)" class="w-22 rounded" />
                                                        </div>
                                                    </div>
                                                </td>
                                                <td scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium text-gray-900 dark:text-white whitespace-nowrap ">
                                                    {{ post.id }} </td>
                                                <td scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium text-gray-900 dark:text-white whitespace-nowrap "
                                                    :title="post.title">
                                                    {{ post.title_limited }} </td>
                                                <td scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium text-gray-900 dark:text-white whitespace-nowrap "
                                                    :title="post.username">
                                                    {{ post.username_limited }} </td>
                                                <td scope="row"
                                                    class="w-4/12 px-6 py-4 font-medium text-gray-900 dark:text-white whitespace-normal break-words "
                                                    :title="post.content">
                                                    {{ post.content_limited }} </td>
                                                <td scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium text-gray-900 dark:text-white whitespace-nowrap ">
                                                    <div v-if="post.permissions.publish && post.permissions.unpublish">
                                                        <input @change="publish(post.id, post.is_active)" type="checkbox"
                                                            true-value="1" false-value="0" v-model="post.is_active"
                                                            class="checkbox checkbox-md checkbox-accent" />
                                                    </div>
                                                </td>
                                                <td scope="row" class="w-1/12 px-6 py-4 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                                    <div class="mb-2" v-if="post.permissions.edit">
                                                        <Link :href="route('posts.edit', post.id)" 
                                                            class="font-bold text-black hover:bg-[#578b87] hover:text-white px-2 py-1 rounded transition">
                                                            Edit
                                                        </Link>
                                                    </div>
                                                    <button v-if="post.permissions.delete" @click="destroy(post.id)"
                                                        class="font-bold text-black hover:bg-red-600 hover:text-white px-2 py-1 rounded transition">
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>


                                </div>
                            </div>
                        </div>
                        <Pagination class="mt-6" :links="posts.links" />
                    </div>
                </div>
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>