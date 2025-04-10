<script setup>
import { ref } from 'vue';

const message = ref('Hello from Vue!');
</script>

<template>
  <div class="max-w-md mx-auto bg-white shadow-lg rounded-lg p-6 mt-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-2">Vue & React in Laravel Inertia</h2>
    <p class="text-gray-600">{{ message }}</p>
    <button 
      @click="message = 'Vue says Hi!'" 
      class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring focus:ring-blue-300">
      Click Me
    </button>
  </div>
</template>
