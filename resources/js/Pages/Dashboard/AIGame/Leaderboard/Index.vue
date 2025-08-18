<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import GameAuthenticatedLayout from '@/Layouts/GameAuthenticated.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { Inertia } from '@inertiajs/inertia';
import DynamicPagination from '@/Components/DynamicPagination.vue';

// Props
const props = defineProps({
  leaderboardData: {
    type: Object,
    default: () => ({ data: [] })
  },
  difficulties: {
    type: Array,
    default: () => []
  },
  categories: {
    type: Array,
    default: () => []
  },
  gameTypes: {
    type: Array,
    default: () => []
  },
  currentFilters: {
    type: Object,
    default: () => ({})
  },
  auth: Object,
});

// Reactive state - initialize from props
const isLoading = ref(false);
const searchQuery = ref(props.currentFilters.search || '');
const includeAI = ref(props.currentFilters.include_ai || false);
const sortField = ref(props.currentFilters.sort_field || 'score');
const sortDirection = ref(props.currentFilters.sort_direction || 'desc');
const perPage = ref(props.currentFilters.per_page || 15);

// Get current page from leaderboard data
const currentPage = computed(() => props.leaderboardData?.current_page || 1);

// Applied filters - get from props instead of parsing URL
const appliedFilters = computed(() => ({
  dateRange: props.currentFilters.start_date && props.currentFilters.end_date 
    ? [new Date(props.currentFilters.start_date), new Date(props.currentFilters.end_date)]
    : [null, null],
  userIds: props.currentFilters.user_ids || [],
  andUsers: props.currentFilters.and_users || false,
  excludeAI: !props.currentFilters.include_ai,
  difficultyId: props.currentFilters.difficulty || null,
  categoryId: props.currentFilters.category || null,
  gameType: props.currentFilters.game_type || null,
  perPage: props.currentFilters.per_page || 15,
}));

// Handle filter changes from GameAuthenticated layout
const handleFilterChange = (event) => {
  const filters = event.detail;
  
  // Build query parameters
  const params = {
    page: 1, // Reset to first page when filters change
    per_page: perPage.value,
    sort_field: sortField.value,
    sort_direction: sortDirection.value,
  };

  if (searchQuery.value.trim()) {
    params.search = searchQuery.value.trim();
  }
  if (includeAI.value) {
    params.include_ai = true;
  }
  if (filters.dateRange && filters.dateRange[0] && filters.dateRange[1]) {
    params.start_date = filters.dateRange[0].toISOString().split('T')[0];
    params.end_date = filters.dateRange[1].toISOString().split('T')[0];
  }
  if (filters.userIds && filters.userIds.length > 0) {
    params.user_ids = filters.userIds.join(',');
    if (filters.andUsers) {
      params.and_users = true;
    }
  }
  if (filters.difficultyId) {
    params.difficulty = filters.difficultyId;
  }
  if (filters.categoryId) {
    params.category = filters.categoryId;
  }
  if (filters.gameType) {
    params.game_type = filters.gameType;
  }

  // Use Inertia to update the page
  Inertia.get('/dashboard/leaderboard', params, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ['leaderboardData', 'currentFilters'] // Only refresh these props
  });
};

// Computed filtered scores based on search (client-side filtering for immediate feedback)
const filteredScores = computed(() => {
  if (!props.leaderboardData?.data) return [];
  
  let filtered = props.leaderboardData.data;
  
  // Note: Server-side search is preferred, but this provides immediate feedback
  if (searchQuery.value.trim() && searchQuery.value !== props.currentFilters.search) {
    const query = searchQuery.value.toLowerCase().trim();
    filtered = filtered.filter(score => 
      score.session_id.toString().toLowerCase().includes(query)
    );
  }
  
  return filtered;
});

// Helper functions
const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatSessionId = (sessionId) => {
  return sessionId.length > 10 ? sessionId.substring(0, 10) + '...' : sessionId;
};

const calculatePercentage = (score) => {
  const maxScore = score.answer_json?.max_score ? parseInt(score.answer_json.max_score) : 100;
  return Math.round((score.score / maxScore) * 100);
};

const getDifficultyColor = (difficulty) => {
  switch (difficulty?.toLowerCase()) {
    case 'easy':
      return 'bg-green-600 text-white';
    case 'medium':
      return 'bg-yellow-600 text-white';
    case 'hard':
    case 'difficult':
      return 'bg-red-600 text-white';
    default:
      return 'bg-gray-600 text-white';
  }
};

const getPercentageColor = (percentage) => {
  if (percentage >= 90) return 'bg-green-500';
  if (percentage >= 70) return 'bg-blue-500';
  if (percentage >= 50) return 'bg-yellow-500';
  if (percentage >= 30) return 'bg-orange-500';
  return 'bg-red-500';
};

// Fetch leaderboard data using Inertia
const fetchLeaderboard = (page = null) => {
  isLoading.value = true;
  
  const params = {
    page: page || currentPage.value,
    per_page: perPage.value,
    sort_field: sortField.value,
    sort_direction: sortDirection.value,
  };

  if (searchQuery.value.trim()) {
    params.search = searchQuery.value.trim();
  }
  if (includeAI.value) {
    params.include_ai = true;
  }
  
  // Add applied filters to params
  if (appliedFilters.value.dateRange[0] && appliedFilters.value.dateRange[1]) {
    params.start_date = appliedFilters.value.dateRange[0].toISOString().split('T')[0];
    params.end_date = appliedFilters.value.dateRange[1].toISOString().split('T')[0];
  }
  if (appliedFilters.value.userIds.length > 0) {
    params.user_ids = appliedFilters.value.userIds.join(',');
    if (appliedFilters.value.andUsers) {
      params.and_users = true;
    }
  }
  if (appliedFilters.value.difficultyId) {
    params.difficulty = appliedFilters.value.difficultyId;
  }
  if (appliedFilters.value.categoryId) {
    params.category = appliedFilters.value.categoryId;
  }
  if (appliedFilters.value.gameType) {
    params.game_type = appliedFilters.value.gameType;
  }

  Inertia.get('/dashboard/leaderboard', params, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ['leaderboardData', 'currentFilters'],
    onFinish: () => {
      isLoading.value = false;
    }
  });
};

// Sort table
const sortTable = (field) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'desc' ? 'asc' : 'desc';
  } else {
    sortField.value = field;
    sortDirection.value = field === 'score' ? 'desc' : 'asc';
  }

  fetchLeaderboard(1);
};

// Change page
const changePage = (page) => {
  fetchLeaderboard(page);
};

// Watch for search changes with debounce
let searchTimeout;
watch(searchQuery, (newValue) => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchLeaderboard(1);
  }, 500);
});

// Watch for AI toggle changes
watch(includeAI, () => {
  fetchLeaderboard(1);
});

// Watch for per page changes
watch(perPage, () => {
  fetchLeaderboard(1);
});

// Watch for prop changes (when filters are applied from GameAuthenticated layout)
watch(() => props.currentFilters, (newFilters, oldFilters) => {
  // Update local state to match new filters, but don't override with defaults
  if (newFilters.search !== undefined) {
    searchQuery.value = newFilters.search || '';
  }
  if (newFilters.include_ai !== undefined) {
    includeAI.value = newFilters.include_ai || false;
  }
  if (newFilters.sort_field !== undefined) {
    sortField.value = newFilters.sort_field || 'score';
  }
  if (newFilters.sort_direction !== undefined) {
    sortDirection.value = newFilters.sort_direction || 'desc';
  }
  if (newFilters.per_page !== undefined) {
    perPage.value = newFilters.per_page || 15;
  }
}, { deep: true });

// Lifecycle
onMounted(() => {
  // Listen for filter changes from GameAuthenticated layout
  window.addEventListener('gameFiltersChanged', handleFilterChange);
});

onUnmounted(() => {
  window.removeEventListener('gameFiltersChanged', handleFilterChange);
  clearTimeout(searchTimeout);
});
</script>

<template>
  <Head title="Leaderboard" />
  <BreezeAuthenticatedLayout>
    <GameAuthenticatedLayout 
      :difficulties="props.difficulties" 
      :categories="props.categories"
      :gameTypes="props.gameTypes"
    >
      <div class="py-4 mb-6 px-2">
        <div class="main-width mx-auto sm:px-6 lg:px-8">
          <!-- Header Section -->
          <div class="mb-2">
            <h2 class="text-3xl font-bold text-white">Game Leaderboard</h2>
          </div>

          <!-- Controls Section -->
          <div class="bg-gray-800 rounded-lg shadow-xl p-6 mb-6">
            <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
              <!-- Search and AI Toggle -->
              <div class="flex flex-col sm:flex-row gap-4 flex-1">
                <div class="relative flex-1 max-w-md">
                  <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by session ID..."
                    class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                  </div>
                </div>

                <div class="flex items-center space-x-3">
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input
                      v-model="includeAI"
                      type="checkbox"
                      class="sr-only peer"
                    />
                    <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    <span class="ml-3 text-sm font-medium text-white">Include AI Scores</span>
                  </label>
                </div>
              </div>

              <!-- Per Page Selector -->
              <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-400">Show:</label>
                <select
                  v-model="perPage"
                  class="px-3 pr-6 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="15">15</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
                <span class="text-sm text-gray-400">per page</span>
              </div>
            </div>
          </div>

          <!-- Leaderboard Table -->
          <div class="bg-gray-800 rounded-lg shadow-xl overflow-hidden">
            <!-- Loading State -->
            <div v-if="isLoading" class="p-8 text-center">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
              <p class="mt-2 text-gray-400">Loading leaderboard...</p>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
              <table class="w-full text-left">
                <thead class="bg-gray-700 border-b border-gray-600">
                  <tr>
                    <th 
                      @click="sortTable('user_name')"
                      class="px-6 py-4 font-semibold text-white cursor-pointer hover:bg-gray-600 transition-colors duration-200"
                    >
                      <div class="flex items-center space-x-2">
                        <span>Player</span>
                        <svg v-if="sortField === 'user_name'" class="w-4 h-4" :class="sortDirection === 'desc' ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                      </div>
                    </th>
                    <th class="px-6 py-4 font-semibold text-white">
                      Game Type
                    </th>
                    <th class="px-6 py-4 font-semibold text-white">
                      Game Session
                    </th>
                    <th class="px-6 py-4 font-semibold text-white">
                      Difficulty
                    </th>
                    <th class="px-6 py-4 font-semibold text-white">
                      Category
                    </th>
                    <th 
                      @click="sortTable('score')"
                      class="px-6 py-4 font-semibold text-white cursor-pointer hover:bg-gray-600 transition-colors duration-200"
                    >
                      <div class="flex items-center space-x-2">
                        <span>Score</span>
                        <svg v-if="sortField === 'score'" class="w-4 h-4" :class="sortDirection === 'desc' ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                      </div>
                    </th>
                    <th class="px-6 py-4 font-semibold text-white">
                      % Score
                    </th>
                    <th 
                      @click="sortTable('created_at')"
                      class="px-6 py-4 font-semibold text-white cursor-pointer hover:bg-gray-600 transition-colors duration-200"
                    >
                      <div class="flex items-center space-x-2">
                        <span>Date Created</span>
                        <svg v-if="sortField === 'created_at'" class="w-4 h-4" :class="sortDirection === 'desc' ? 'rotate-180' : ''" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-gray-800">
                  <tr 
                    v-for="(score, index) in filteredScores" 
                    :key="score.id || `ai-${score.session_id}-${index}`"
                    class="border-b border-gray-700 hover:bg-gray-750 transition-colors duration-200"
                    :class="{ 'bg-blue-900/20': score.score_type === 'ai' }"
                  >
                    <!-- Player Name -->
                    <td class="px-6 py-4">
                      <div class="flex items-center space-x-3">
                        <div 
                          class="w-2 h-2 rounded-full"
                          :class="score.score_type === 'ai' ? 'bg-blue-500' : 'bg-green-500'"
                        ></div>
                        <span class="text-white font-medium">
                          {{ score.user.name }}
                          <span v-if="includeAI && score.user.name === 'AI'" class="text-gray-400"> - Normal</span>
                        </span>
                      </div>
                    </td>

                    <!-- Game Type -->
                    <td class="px-6 py-4">
                      <span class="text-gray-300 font-mono text-sm">
                        {{ score.game_type_name }}
                      </span>
                    </td>

                    <!-- Game Session -->
                    <td class="px-6 py-4">
                      <span class="text-gray-300 font-mono text-sm">
                        {{ formatSessionId(score.session_id) }}
                      </span>
                    </td>

                    <!-- Difficulty -->
                    <td class="px-6 py-4">
                      <span 
                        class="px-2 py-1 text-xs font-medium rounded-full"
                        :class="getDifficultyColor(score.answer_json?.difficulty_name)"
                      >
                        {{ score.answer_json?.difficulty_name || 'N/A' }}
                      </span>
                    </td>

                    <!-- Category -->
                    <td class="px-6 py-4">
                      <span class="text-gray-300">
                        {{ score.answer_json?.category_name || 'N/A' }}
                      </span>
                    </td>

                    <!-- Score -->
                    <td class="px-6 py-4">
                      <div class="flex items-center space-x-2">
                        <span class="text-white font-bold text-lg">{{ score.score }}</span>
                        <span class="text-gray-400 text-sm">pts</span>
                      </div>
                    </td>

                    <!-- Percentage Score -->
                    <td class="px-6 py-4">
                      <div class="flex items-center space-x-3">
                        <div class="flex-1 bg-gray-700 rounded-full h-2 max-w-[100px]">
                          <div 
                            class="h-2 rounded-full transition-all duration-300"
                            :class="getPercentageColor(calculatePercentage(score))"
                            :style="{ width: Math.min(calculatePercentage(score), 100) + '%' }"
                          ></div>
                        </div>
                        <span class="text-white font-medium min-w-[3rem] text-right">
                          {{ calculatePercentage(score) }}%
                        </span>
                      </div>
                    </td>

                    <!-- Date Created -->
                    <td class="px-6 py-4">
                      <span class="text-gray-300">{{ formatDate(score.created_at) }}</span>
                    </td>
                  </tr>

                  <!-- Empty State -->
                  <tr v-if="!filteredScores.length && !isLoading">
                    <td colspan="8" class="px-6 py-12 text-center">
                      <div class="text-gray-400">
                        <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-lg font-medium mb-1">No scores found</p>
                        <p class="text-sm">Try adjusting your search or filters</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div v-if="leaderboardData && leaderboardData.last_page > 1" class="px-6 py-4 border-t border-gray-700">
              <DynamicPagination 
                :currentPage="currentPage" 
                :totalPages="leaderboardData.last_page"
                @change-page="changePage" 
              />
            </div>
          </div>
        </div>
      </div>
    </GameAuthenticatedLayout>
  </BreezeAuthenticatedLayout>
</template>

<style scoped>
/* Custom scrollbar for table */
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: #374151;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background: #6B7280;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #9CA3AF;
}

/* Hover effect for table rows */
.hover\:bg-gray-750:hover {
  background-color: #374151;
}

/* Animation for loading spinner */
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.animate-spin {
  animation: spin 1s linear infinite;
}

/* Custom toggle switch styles */
input[type="checkbox"]:checked + div {
  background-color: #3B82F6;
}

input[type="checkbox"]:checked + div::after {
  transform: translateX(100%);
}

/* Smooth transitions */
* {
  transition: all 0.2s ease-in-out;
}
</style>