<script setup>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated.vue";
import { Head } from "@inertiajs/inertia-vue3";
import BreezeButton from "@/Components/Button.vue";
import { Link } from "@inertiajs/inertia-vue3";
import { useForm } from "@inertiajs/inertia-vue3";
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
    posts: {
        type: Object,
        default: () => ({}),
    },
    dialogueData: {
        type: Object,
        default: () => ({
            calculationResults: {},
            aiResponseResults: {},
            chatbotMessages: [],
        }),
    },
});

const form = useForm({
    title: '',
    slug: '',
    content: '',
    is_active: 0,
    featured_image: null,
});

// Set the content field with the dialogueData when the component is mounted
onMounted(() => {
    const { calculationResults, aiResponseResults, chatbotMessages } = props.dialogueData;
    
    let content = '';

    if (calculationResults.city) {
        content += `City: ${calculationResults.city}`;
    }

    if (calculationResults && calculationResults.trim() !== '') {
        content += calculationResults;
    }

    if (aiResponseResults && aiResponseResults.trim() !== '') {
        content += '\n\n' + aiResponseResults;
    }

    // Add Chatbot Messages if they exist and aren't empty
    if (chatbotMessages && chatbotMessages.trim() !== '') {
        content += '\n\n' + chatbotMessages;
    }
    
    // Set the form content
    form.content = content;
    
    // Store raw data for submission
    form.calculationResults = calculationResults;
    form.aiResponseResults = aiResponseResults;
    form.chatbotMessages = chatbotMessages;
});

const submit = () => {
    form.post(route("posts.store"));
};

// Utility function to get the correct image URL
const getImageUrl = (imagePath, postId = null) => {
    const isProduction = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';
    
    if (!imagePath) {
        return isProduction && import.meta.env.VITE_AWS_BUCKET
            ? `https://${import.meta.env.VITE_AWS_BUCKET}.s3.amazonaws.com/images/example-image.png`
            : '/images/example-image.png';
    }
    
    if (isProduction && import.meta.env.VITE_AWS_BUCKET) {
        return `/${imagePath}`;
    }
    
    return `/storage/${imagePath}`;
};

// Create.vue
const temp_image = ref(getImageUrl(null));

function fileChange(event) {
    // Clear any previous object URL to avoid memory leaks
    // if (temp_image.value && temp_image.value.startsWith('blob:')) {
    //     URL.revokeObjectURL(temp_image.value);
    // }

    // Set the new image as the preview
    const file = event.target.files[0];
    if (file) {
        temp_image.value = URL.createObjectURL(file); // Update the preview
        form.featured_image = file; // Update the form with the selected file
    }
}

</script>

<template>

    <Head title="Post Create" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="text-md font-semibold leading-tight text-white">
                Create Post
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-5 border-b border-gray-200">

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
                                <input ref="featured_image" style="display:none" type="file" @change="fileChange"
                                    @input="form.featured_image = $event.target.files[0]" />
                            </div>
                            <div class="mb-6">
                                <label for="Title"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">Title</label>
                                <input type="text" v-model="form.title" name="title"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="" />
                                <div v-if="form.errors.title" class="text-sm text-red-600">
                                    {{ form.errors.title }}
                                </div>
                            </div>
                            <div class="mb-6">
                                <label for="Slug"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">Slug</label>
                                <input type="text" v-model="form.slug" name="title"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="" />
                                <div v-if="form.errors.slug" class="text-sm text-red-600">
                                    {{ form.errors.slug }}
                                </div>
                            </div>
                            <div class="mb-6">
                                <label for="Content" 
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">
                                    Content
                                </label>
                                <textarea 
                                    v-model="form.content"
                                    name="content" 
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 h-60"
                                ></textarea>
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
                                            class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">Published</label>
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