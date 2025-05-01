<template>
  <div v-if="totalPages > 1" class="mt-6 flex justify-center items-center space-x-1 flex-wrap max-w-md mx-auto">
    <!-- First Button -->
    <button
      @click="changePage(1)"
      :disabled="currentPage === 1"
      class="px-2 py-1 text-sm bg-gray-300 text-gray-900 rounded-l-md disabled:opacity-50"
    >
      First
    </button>

    <!-- Previous Button -->
    <button
      @click="changePage(currentPage - 1)"
      :disabled="currentPage === 1"
      class="px-2 py-1 text-sm bg-gray-300 text-gray-900 disabled:opacity-50"
    >
      Prev
    </button>

    <!-- Page Numbers with Ellipsis -->
    <template v-for="(page, index) in displayedPageNumbers" :key="index">
      <span v-if="page === '...'" class="px-2 py-1 text-gray-500">...</span>
      <button
        v-else
        @click="changePage(page)"
        :class="{'bg-blue-500 text-white': page === currentPage, 'bg-gray-300 text-gray-900': page !== currentPage}"
        class="px-2 py-1 text-sm"
      >
        {{ page }}
      </button>
    </template>

    <!-- Next Button -->
    <button
      @click="changePage(currentPage + 1)"
      :disabled="currentPage === totalPages"
      class="px-2 py-1 text-sm bg-gray-300 text-gray-900 disabled:opacity-50"
    >
      Next
    </button>

    <!-- Last Button -->
    <button
      @click="changePage(totalPages)"
      :disabled="currentPage === totalPages"
      class="px-2 py-1 text-sm bg-gray-300 text-gray-900 rounded-r-md disabled:opacity-50"
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

// Calculate page numbers to display with ellipsis for better mobile experience
const displayedPageNumbers = computed(() => {
  const maxVisiblePages = 5; // Adjust based on your preference
  const pages = [];

  if (props.totalPages <= maxVisiblePages) {
    // Show all pages if there aren't many
    for (let i = 1; i <= props.totalPages; i++) {
      pages.push(i);
    }
  } else {
    // Always include first page
    pages.push(1);

    // Calculate start and end of displayed pages
    let startPage = Math.max(2, props.currentPage - 1);
    let endPage = Math.min(props.totalPages - 1, props.currentPage + 1);

    // Add ellipsis before middle pages if needed
    if (startPage > 2) {
      pages.push('...');
    }

    // Add middle pages
    for (let i = startPage; i <= endPage; i++) {
      pages.push(i);
    }

    // Add ellipsis after middle pages if needed
    if (endPage < props.totalPages - 1) {
      pages.push('...');
    }

    // Always include last page
    pages.push(props.totalPages);
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