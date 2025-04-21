<script setup>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated.vue";
import { Head } from "@inertiajs/inertia-vue3";
import BreezeButton from "@/Components/Button.vue";
import { Link } from "@inertiajs/inertia-vue3";
import { useForm } from "@inertiajs/inertia-vue3";
import { Inertia } from '@inertiajs/inertia';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    post: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    id: props.post.id,
    title: props.post.title,
    slug: props.post.slug,
    content: props.post.content,
    is_active: props.post.is_active,
    featured_image: null,  // This will hold the new file if uploaded
});

// Utility function to get the correct image URL
const getImageUrl = (imageName, postId = null) => {
    const isProduction = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';
    const bucket = import.meta.env.VITE_AWS_BUCKET;

    if (!imageName) {
        return isProduction && bucket
            ? `https://${import.meta.env.VITE_AWS_BUCKET}.s3.amazonaws.com/images/example-image.png`
            : '/images/example-image.png';
    }

    console.log('EDIT');
    console.log('imageName', imageName);
    console.log('postId', postId);
    console.log('isProduction', isProduction);
    console.log('VITE_AWS_BUCKET', bucket);

    if (isProduction && bucket) {
    console.log('HERE EDIT PROD: ' + imageName);
        return imageName;
    }

    return `${imageName}`;
};

// Create a reactive ref to hold the image URL
const imageUrl = ref(null);

const temp_image = computed(() => {

    console.log('temp_image', imageUrl.value);
    if (imageUrl.value) {
        console.log('imageUrl.value', imageUrl.value);
        return imageUrl.value;
    }
    console.log('props.post.featured_image', props.post.featured_image);
    return getImageUrl(props.post.featured_image, props.post.id);
});

function fileChange(event) {
    // Clear any previous object URL to avoid memory leaks
    if (imageUrl.value && imageUrl.value.startsWith('blob:')) {
        URL.revokeObjectURL(imageUrl.value);
    }

    // Get the selected file
    const file = event.target.files[0];

    console.log('file', file);


    if (file) {
        
        // Set the local image preview using URL.createObjectURL()
        // This is ONLY for preview purposes
        imageUrl.value = URL.createObjectURL(file);
        
        // Set the file for form submission - this is the important part

        console.log('form.featured_image', form.featured_image);
        form.featured_image = file;
        
        // Log for debugging
        console.log('File set for upload:', file.name);
    }
}


// Handle form submission
const submit = () => {
    // If we're using FormData with files, we need to create it manually
    // to ensure proper file handling in all environments
    const formData = new FormData();
    
    // Add all form fields
    formData.append('id', form.id);
    formData.append('title', form.title);
    formData.append('slug', form.slug);
    formData.append('content', form.content);
    formData.append('is_active', form.is_active);
    formData.append('_method', 'POST');
    
    // Add the file if it exists - this is the critical part
    if (form.featured_image) {
        formData.append('featured_image', form.featured_image);
        console.log('Appended file to form data:', form.featured_image.name);
    }
    
    // Use Inertia directly with the FormData object
    Inertia.post(route('posts.update', props.post.id), formData, {
        forceFormData: true,
        onSuccess: () => {
            console.log('Form submitted successfully with manual FormData');
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
        }
    });
};


onMounted(() => {
    if (props.post.featured_image) {
        imageUrl.value = props.post.featured_image.replace(`.s3.eu-west-2.amazonaws.com`, `.s3.amazonaws.com`);
        console.log('imageUrl.value', imageUrl.value);
    }
});


</script>


<template>

    <Head title="Post Edit" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="text-md font-semibold leading-tight text-white">
                Edit Post
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-5 border-b border-gray-200">
                        <input type="hidden" v-model="form.id">

                        <input type="checkbox" id="my-error-modal" class="modal-toggle" v-model="form.hasErrors" />
                        <div class="modal">
                            <div class="modal-box relative">
                                <label for="my-error-modal"
                                    class="btn btn-sm btn-circle absolute right-2 top-2">✕</label>
                                <h3 class="text-lg font-bold">Error Message!</h3>
                                <p class="py-4">Something went wrong!</p>
                            </div>
                        </div>

                        <form @submit.prevent="submit">
                            <div class="avatar">
                                <div class="w-44 rounded">
                                    <img :src="temp_image" />
                                </div>
                            </div>
                            <div class="mb-6">
                                <div>
                                    <progress class="progress progress-success w-44" v-if="form.progress"
                                        :value="form.progress.percentage" max="100">
                                        {{ form.progress.percentage }}%
                                    </progress>
                                </div>
                                <button @click="$refs.featured_image.click()" type="button"
                                    class="btn btn-outline btn-primary btn-sm w-44">Choose Image</button>
                                <input ref="featured_image" style="display:none" type="file" @change="fileChange" />
                            </div>
                            <div class="mb-6">
                                <label for="Title"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">Title</label>
                                <input type="text" v-model="form.title"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="" />
                                <div v-if="form.errors.title" class="text-sm text-red-600">
                                    {{ form.errors.title }}
                                </div>
                            </div>
                            <div class="mb-6">
                                <label for="Slug"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">Slug</label>
                                <input type="text" v-model="form.slug"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="" />
                                <div v-if="form.errors.slug" class="text-sm text-red-600">
                                    {{ form.errors.slug }}
                                </div>
                            </div>
                            <div class="mb-6">
                                <label for="slug"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">Content</label>
                                <textarea type="text" v-model="form.content"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 h-60"></textarea>

                                <div v-if="form.errors.content" class="text-sm text-red-600">
                                    {{ form.errors.content }}
                                </div>
                            </div>
                            <div class="mb-6">
                                <div class="flex">
                                    <div class="flex items-center mr-4">
                                        <input id="inline-radio" type="radio" value="0" v-model="form.is_active"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <label for="inline-radio"
                                            class="ml-2 text-sm font-medium text-white">Not Published</label>
                                    </div>
                                    <div class="flex items-center mr-4">
                                        <input id="inline-2-radio" type="radio" value="1" v-model="form.is_active"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <label for="inline-2-radio"
                                            class="ml-2 text-sm font-medium text-white">Published</label>
                                    </div>
                                </div>

                                <div v-if="form.errors.is_active" class="text-sm text-red-600">
                                    {{ form.errors.is_active }}
                                </div>
                            </div>
                            <button type="submit"
                                class="text-white bg-blue-700  focus:outline-none  font-medium rounded-lg text-sm px-5 py-2.5 "
                                :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                                Submit
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>