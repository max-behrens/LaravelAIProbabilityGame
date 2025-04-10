<template>
  <app-layout title="Angular Demo">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Angular Demo
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <div id="angular-app" ref="angularAppRef">
            <app-user></app-user>
          </div>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import { defineComponent, onMounted } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head } from '@inertiajs/inertia-vue3';

export default defineComponent({
  components: {
    BreezeAuthenticatedLayout,
    Head,
  },
  setup() {
    onMounted(async () => {
      // Dynamically import Angular application
      const angularApp = await import('../../../public/angular-app/browser/main.js');

      // Initialize Angular
      if (typeof angularApp.initializeAngular === 'function') {
        angularApp.initializeAngular();
      }
    });

    return {};
  },
});
</script>