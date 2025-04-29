<template>
    <div v-if="totalPages > 1" class="mt-6 flex justify-center items-center space-x-2">
      <!-- First Button -->
      <button
        @click="changePage(1)"
        :disabled="currentPage === 1"
        class="px-4 py-2 bg-gray-300 text-gray-900 rounded-l-md disabled:opacity-50"
      >
        First
      </button>
  
      <!-- Previous Button -->
      <button
        @click="changePage(currentPage - 1)"
        :disabled="currentPage === 1"
        class="px-4 py-2 bg-gray-300 text-gray-900 disabled:opacity-50"
      >
        Previous
      </button>
  
      <!-- Page Number Buttons -->
      <button
        v-for="page in pageNumbers"
        :key="page"
        @click="changePage(page)"
        :class="{'bg-blue-500 text-gray-900': page === currentPage, 'bg-gray-300 text-gray-900': page !== currentPage}"
        class="px-4 py-2"
      >
        {{ page }}
      </button>
  
      <!-- Next Button -->
      <button
        @click="changePage(currentPage + 1)"
        :disabled="currentPage === totalPages"
        class="px-4 py-2 bg-gray-300 text-gray-900 disabled:opacity-50"
      >
        Next
      </button>
  
      <!-- Last Button -->
      <button
        @click="changePage(totalPages)"
        :disabled="currentPage === totalPages"
        class="px-4 py-2 bg-gray-300 text-gray-900 rounded-r-md disabled:opacity-50"
      >
        Last
      </button>
    </div>
  </template>
  
  <script setup>
  import { ref, computed } from 'vue';
  
  // Props received from parent
  const props = defineProps({
    currentPage: Number,
    totalPages: Number,
  });
  
  // Emit event to change page
  const emit = defineEmits(['change-page']);
  
  // Calculate page numbers to display
  const pageNumbers = computed(() => {
    const pages = [];
    for (let i = 1; i <= props.totalPages; i++) {
      pages.push(i);
    }
    return pages;
  });
  
  // Handle page change
  const changePage = (page) => {
    if (page >= 1 && page <= props.totalPages) {
      emit('change-page', page);
    }
  };
  </script>
  
  <style scoped>
  /* Add your styles for pagination buttons here */
  </style>
  