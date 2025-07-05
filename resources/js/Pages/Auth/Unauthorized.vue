<script setup>
import { Inertia } from '@inertiajs/inertia';

// Go back to the previous page
const goBack = () => {
  window.history.back();
};

// Go to dashboard/home
const goHome = () => {
  Inertia.visit('/dashboard');
};
</script>

<template>
  <div class="min-h-screen bg-gray-900 flex items-center justify-center px-4">
    <div class="max-w-md w-full bg-gray-800 rounded-lg shadow-lg p-8 text-center">
      <!-- Error Icon -->
      <div class="mb-6">
        <svg class="mx-auto h-24 w-24 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
      </div>

      <!-- Error Message -->
      <h1 class="text-3xl font-bold text-red-500 mb-4">
        Access Denied
      </h1>
      
      <p class="text-gray-300 mb-8 leading-relaxed">
        You don't have permission to access this page. Please contact your administrator if you believe this is an error.
      </p>

      <!-- Action Buttons -->
      <div class="space-y-4">
        <button
          @click="goBack"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 ease-in-out transform hover:scale-105"
        >
          Go Back
        </button>
        
        <button
          @click="goHome"
          class="w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 ease-in-out"
        >
          Go to Dashboard
        </button>
      </div>

      <!-- Additional Info -->
      <div class="mt-8 pt-6 border-t border-gray-700">
        <p class="text-sm text-gray-400">
          Error Code: 403 - Forbidden
        </p>
      </div>
    </div>
  </div>
</template>