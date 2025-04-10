<script setup>
import { Head } from '@inertiajs/inertia-vue3';
import VueComponent from '@/Components/VueComponent.vue';
import ReactComponentWrapper from '@/Components/ReactComponentWrapper.vue';
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated.vue";
</script>

<template>
    <Head title="React" />
    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="text-md font-semibold leading-tight text-gray-800">React Page</h2>
        </template>
        <div class="py-12">
            <VueComponent />
            <ReactComponentWrapper componentName="ReactComponent" />
        </div>
    </BreezeAuthenticatedLayout>
</template>