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

onMounted(() => {
    console.log('Create route URL:', route('posts.create'));
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
    const isProduction = window.location.hostname !== 'localhost'
                         && window.location.hostname !== '127.0.0.1';

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
    return `${post.featured_image}`;
};
 

</script>
<template>
    <Head title="Posts" />
    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="text-md font-semibold leading-tight text-white"> User Posts </h2>
        </template>
        <div class="py-12 main-width mx-auto sm:px-6 lg:px-8">
                <div
                  v-if="$page.props.flash.message"
                  class="alert alert-success shadow-lg mb-5 bg-green-900 text-green-300 border border-green-700"
                >
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $page.props.flash.message }}</span>
                    </div>
                </div>

                    <div class="p-5 border-b border-gray-700">
                        <div class="relative">
                            <div class="block md:block">

                                <!-- Mobile view -->
                                <div class="block md:hidden">
                                    <div
                                      v-for="post in posts.data"
                                      :key="post.id"
                                      class="mb-4 rounded-lg shadow-md p-4 bg-gray-800 border border-gray-700"
                                    >
                                        <div class="grid grid-cols-1 gap-2 break-words text-gray-300">
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
                                                <div class="font-semibold text-gray-200">ID:</div>
                                                <div>{{ post.id }}</div>

                                                <div class="font-semibold text-gray-200">Title:</div>
                                                <div>{{ post.title_limited }}</div>

                                                <div class="font-semibold text-gray-200">Username:</div>
                                                <div>{{ post.username_limited }}</div>

                                                <div class="font-semibold text-gray-200">Content:</div>
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
                                                        class="btn btn-warning btn-sm">
                                                        Edit
                                                    </Link>
                                                    <button v-if="post.permissions.delete"
                                                        @click="destroy(post.id)"
                                                        class="btn btn-error btn-sm">
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Desktop view -->
                                <div class="hidden md:block">
                                    <table class="table-compact w-full text-center border-collapse bg-gray-900 text-gray-300 border border-gray-700">
                                        <thead
                                            class="text-xs uppercase bg-gray-700 text-gray-400 sticky top-0">
                                            <tr>
                                                <th colspan="4" class="w-1/2 px-6 py-3 text-left">
                                                    <Link :href="route('posts.create')"
                                                        class="text-white bg-[#578b87] px-4 py-3 rounded hover:bg-[#406865] transition">
                                                        + Add Post
                                                    </Link>
                                                </th>
                                                <th colspan="3" class="w-1/2 px-6 py-3 text-right">
                                                    <input id="default-search" type="text" v-debounce:300="setSearchInput"
                                                        @keydown="setSearchInput($event.target.value, $event)"
                                                        class="input w-full max-w-xs placeholder-gray-400 bg-gray-800 text-gray-200 border border-gray-600"
                                                        placeholder="Search..." />
                                                </th>
                                            </tr>
                                            <tr>
                                                <th class="text-white w-1/12 sticky top-0 bg-gray-700">Image</th>
                                                <th class="text-white w-1/12 sticky top-0 bg-gray-700" scope="col" @click="sort('id')">
                                                    <span class="inline-flex px-6 py-3 w-full justify-center cursor-pointer select-none">
                                                        #
                                                        <SortArrowUp v-if="determineSortDirection('id', 'asc')" />
                                                        <SortArrowDown v-if="determineSortDirection('id', 'desc')" />
                                                    </span>
                                                </th>
                                                <th class="text-white w-1/12 sticky top-0 bg-gray-700" scope="col" @click="sort('title')">
                                                    <span class="inline-flex px-6 py-3 w-full justify-center cursor-pointer select-none">
                                                        Title
                                                        <SortArrowUp v-if="determineSortDirection('title', 'asc')" />
                                                        <SortArrowDown v-if="determineSortDirection('title', 'desc')" />
                                                    </span>
                                                </th>
                                                <th class="text-white w-1/12 sticky top-0 bg-gray-700" scope="col" @click="sort('username')">
                                                    <span class="inline-flex px-6 py-3 w-full justify-center cursor-pointer select-none">
                                                        Username
                                                        <SortArrowUp v-if="determineSortDirection('username', 'asc')" />
                                                        <SortArrowDown v-if="determineSortDirection('username', 'desc')" />
                                                    </span>
                                                </th>
                                                <th class="text-white w-4/12 sticky top-0 bg-gray-700" scope="col" @click="sort('content')">
                                                    <span class="inline-flex px-6 py-3 w-full justify-center items-center text-center cursor-pointer select-none">
                                                        Content
                                                        <SortArrowUp v-if="determineSortDirection('content', 'asc')" />
                                                        <SortArrowDown v-if="determineSortDirection('content', 'desc')" />
                                                    </span>
                                                </th>
                                                <th class="text-white w-1/12 sticky top-0 bg-gray-700">Publish</th>
                                                <th class="text-white w-1/12 sticky top-0 bg-gray-700">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="post in posts.data"
                                                :key="post.id"
                                                class="bg-gray-800 border-b border-gray-700 hover:bg-gray-700"
                                            >
                                                <td
                                                    scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium whitespace-normal break-words"
                                                >
                                                    <div class="avatar">
                                                        <div class="w-16 rounded">
                                                            <img :src="getPostImage(post)" class="w-16 rounded" />
                                                        </div>
                                                    </div>
                                                </td>
                                                <td
                                                    scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium whitespace-nowrap"
                                                >
                                                    {{ post.id }}
                                                </td>
                                                <td
                                                    scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium whitespace-nowrap"
                                                    :title="post.title"
                                                >
                                                    {{ post.title_limited }}
                                                </td>
                                                <td
                                                    scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium whitespace-nowrap"
                                                    :title="post.username"
                                                >
                                                    {{ post.username_limited }}
                                                </td>
                                                <td
                                                    scope="row"
                                                    class="w-4/12 px-6 py-4 font-medium whitespace-normal break-words"
                                                    :title="post.content"
                                                >
                                                    {{ post.content_limited }}
                                                </td>
                                                <td
                                                    scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium whitespace-nowrap"
                                                >
                                                    <div v-if="post.permissions.publish && post.permissions.unpublish">
                                                        <input
                                                            @change="publish(post.id, post.is_active)"
                                                            type="checkbox"
                                                            true-value="1"
                                                            false-value="0"
                                                            v-model="post.is_active"
                                                            class="checkbox checkbox-md checkbox-accent"
                                                        />
                                                    </div>
                                                </td>
                                                <td
                                                    scope="row"
                                                    class="w-1/12 px-6 py-4 font-medium whitespace-nowrap"
                                                >
                                                    <div class="mb-2" v-if="post.permissions.edit">
                                                        <Link
                                                            :href="route('posts.edit', post.id)"
                                                            class="font-bold text-gray-300 hover:bg-[#578b87] hover:text-white px-2 py-1 rounded transition"
                                                        >
                                                            Edit
                                                        </Link>
                                                    </div>
                                                    <button
                                                        v-if="post.permissions.delete"
                                                        @click="destroy(post.id)"
                                                        class="font-bold text-gray-300 hover:bg-red-600 hover:text-white px-2 py-1 rounded transition"
                                                    >
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                    <Pagination class="mt-6" :links="posts.links" />
        </div>
    </BreezeAuthenticatedLayout>
</template>
