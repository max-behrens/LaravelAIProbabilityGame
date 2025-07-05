<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { ref } from 'vue';
import ChartComponent from '@/Components/ChartComponent.vue';

</script>

<template>
    <Head title="Dashboard" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-md text-white leading-tight">Dashboard</h2>
        </template>

        <div class="py-12 main-width mx-auto sm:px-6 lg:px-8">
            <div class="p-5">

                <!-- Chart Component -->
                <ChartComponent />
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>
