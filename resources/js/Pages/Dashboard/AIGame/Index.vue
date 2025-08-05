<script setup>
import { ref, watchEffect, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import GameAuthenticatedLayout from '@/Layouts/GameAuthenticated.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import { useGames } from '@/Composables/useGames';
import DynamicPagination from '@/Components/DynamicPagination.vue';
import axios from 'axios';

const props = defineProps({
  games: {
    type: Array,
    default: () => []
  },
  user: {
    type: Object,
    required: true
  },
});

// Destructure the composable properly with error handling
const { 
  games: liveGames, 
  paginationMeta, 
  error: gamesError, 
  fetchGames 
} = useGames();

// Local state
const gamesData = ref([]);
const playersCount = ref({});
const userInGames = ref({});
const errorMessage = ref('');
const successMessage = ref('');
const isLoading = ref(false);

// Internal states for pagination
const currentPage = ref(1);

// Filter states - get initial values from URL
const getFiltersFromURL = () => {
  try {
    const urlParams = new URLSearchParams(window.location.search);
    return {
      start_date: urlParams.get('start_date'),
      end_date: urlParams.get('end_date'),
      user_ids: urlParams.get('user_ids'),
      and_users: urlParams.get('and_users') === 'true'
    };
  } catch (error) {
    console.error('Error parsing URL filters:', error);
    return {
      start_date: null,
      end_date: null,
      user_ids: null,
      and_users: false
    };
  }
};

const currentFilters = ref(getFiltersFromURL());

// Initialize with props and then update with live data
onMounted(async () => {
  try {
    // Initialize with props if available
    if (props.games && Array.isArray(props.games)) {
      updateGameState(props.games);
    }
    
    // Fetch games with current filters
    await fetchGamesWithFilters();
    
    // Listen for filter changes from the navigation
    window.addEventListener('gameFiltersChanged', handleFilterChange);
  } catch (error) {
    console.error('Error during component mount:', error);
    errorMessage.value = 'Failed to initialize games';
  }
});

const handleFilterChange = async (event) => {
  try {
    const { dateRange, userIds, andUsers } = event.detail;
    
    // Update current filters
    currentFilters.value = {
      start_date: dateRange && dateRange[0] ? dateRange[0].toISOString().split('T')[0] : null,
      end_date: dateRange && dateRange[1] ? dateRange[1].toISOString().split('T')[0] : null,
      user_ids: userIds && userIds.length > 0 ? userIds.join(',') : null,
      and_users: andUsers
    };
    
    // Reset to page 1 when filters change
    currentPage.value = 1;
    
    // Fetch games with new filters
    await fetchGamesWithFilters();
  } catch (error) {
    console.error('Error handling filter change:', error);
    errorMessage.value = 'Failed to apply filters';
  }
};

const fetchGamesWithFilters = async () => {
  if (isLoading.value) return; // Prevent concurrent requests
  
  try {
    isLoading.value = true;
    errorMessage.value = '';
    
    // Clean up filters - remove null/undefined values
    const filters = {};
    if (currentFilters.value.start_date) filters.start_date = currentFilters.value.start_date;
    if (currentFilters.value.end_date) filters.end_date = currentFilters.value.end_date;
    if (currentFilters.value.user_ids) filters.user_ids = currentFilters.value.user_ids;
    if (currentFilters.value.and_users) filters.and_users = currentFilters.value.and_users;
    
    await fetchGames(currentPage.value, filters);
  } catch (error) {
    console.error('Error fetching games with filters:', error);
    errorMessage.value = 'Failed to fetch games';
  } finally {
    isLoading.value = false;
  }
};

// Watch for page changes
watch(currentPage, async (newPage, oldPage) => {
  if (newPage !== oldPage) {
    await fetchGamesWithFilters();
  }
});

// Update local game state from the list of games
const updateGameState = (gamesList) => {
  if (!Array.isArray(gamesList)) {
    console.warn('updateGameState called with non-array:', gamesList);
    return;
  }

  gamesData.value = gamesList;

  // Update players count and user participation for each game
  gamesList.forEach(game => {
    if (game && game.id) {
      playersCount.value[game.id] = game.players_count || 0;
      
      // Check if current user is in this game
      if (game.users && Array.isArray(game.users)) {
        userInGames.value[game.id] = game.users.some(u => u && u.id === props.user?.id);
      } else {
        // Fallback if users not populated
        userInGames.value[game.id] = false;
      }
    }
  });
};

// Watch liveGames from composable and update local state
watchEffect(() => {
  if (liveGames.value && Array.isArray(liveGames.value)) {
    updateGameState(liveGames.value);
  }
});

// Use liveGames from the composable instead of games.value
const currentPageGames = computed(() => {
  return Array.isArray(liveGames.value) ? liveGames.value : [];
});

const totalPages = computed(() => {
  return paginationMeta.value?.lastPage || 1;
});

// Change the current page
const changePage = (page) => {
  if (page >= 1 && page <= (paginationMeta.value?.lastPage || 1)) {
    currentPage.value = page;
  }
}

const joinGame = async (gameId) => {
  if (!gameId || isLoading.value) return;
  
  errorMessage.value = '';
  successMessage.value = '';

  try {
    isLoading.value = true;
    
    await axios.get('/sanctum/csrf-cookie');
    const response = await axios.post(`/games/${gameId}/join`);

    if (response.data && response.data.success) {
      // Update local state
      userInGames.value[gameId] = true;
      playersCount.value[gameId] = (playersCount.value[gameId] || 0) + 1;

      // Refresh games
      await fetchGamesWithFilters();

      successMessage.value = 'Successfully joined the game!';

      // Navigate to game room
      if (props.user?.id) {
        window.location.href = `/dashboard/room/${gameId}/${props.user.id}`;
      }
    }
  } catch (err) {
    console.error('Error joining game:', err);
    errorMessage.value = err.response?.data?.message || 'Error joining game';
  } finally {
    isLoading.value = false;
  }
};

const leaveGame = async (gameId) => {
  if (!gameId || isLoading.value) return;
  
  errorMessage.value = '';
  successMessage.value = '';

  try {
    isLoading.value = true;
    
    await axios.get('/sanctum/csrf-cookie');
    const response = await axios.post(`/games/${gameId}/leave`);

    if (response.data && response.data.success) {
      // Update local state
      userInGames.value[gameId] = false;
      playersCount.value[gameId] = Math.max(0, (playersCount.value[gameId] || 1) - 1);

      // Refresh games
      await fetchGamesWithFilters();

      successMessage.value = 'Successfully left the game!';
    }
  } catch (err) {
    console.error('Error leaving game:', err);
    errorMessage.value = err.response?.data?.message || 'Error leaving game';
  } finally {
    isLoading.value = false;
  }
};

const enterGame = (gameId) => {
  if (gameId && props.user?.id) {
    window.location.href = `/dashboard/room/${gameId}/${props.user.id}`;
  }
};

// Find current game ID (if user is in a game)
const currentGameId = computed(() => {
  const userGames = Object.keys(userInGames.value).filter(gameId => userInGames.value[gameId]);
  return userGames.length > 0 ? parseInt(userGames[0]) : null;
});

// Cleanup event listener
onUnmounted(() => {
  try {
    window.removeEventListener('gameFiltersChanged', handleFilterChange);
  } catch (error) {
    console.error('Error during cleanup:', error);
  }
});

// Auto-clear messages after 5 seconds
watch([errorMessage, successMessage], () => {
  if (errorMessage.value || successMessage.value) {
    setTimeout(() => {
      errorMessage.value = '';
      successMessage.value = '';
    }, 5000);
  }
});
</script>

<template>
  <Head title="AI Game Dashboard" />

  <BreezeAuthenticatedLayout>
    <GameAuthenticatedLayout :currentGameId="currentGameId">
      <template #header>
        <h2 class="font-semibold text-md text-white leading-tight">AI Game Lobby</h2>
      </template>

      <div class="py-4">
        <div class="main-width mx-auto sm:px-6 lg:px-8">
          <!-- Error Messages -->
          <div v-if="errorMessage" class="mb-4 p-4 bg-red-900 text-red-200 rounded border border-red-700">
            {{ errorMessage }}
          </div>

          <div v-if="successMessage" class="mb-4 p-4 bg-green-900 text-green-200 rounded border border-green-700">
            {{ successMessage }}
          </div>

          <!-- Loading State -->
          <div v-if="isLoading" class="mb-4 p-4 bg-blue-900 text-blue-200 rounded border border-blue-700">
            Loading games...
          </div>

          <!-- Games Grid -->
          <div v-if="currentPageGames.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div 
              v-for="game in currentPageGames" 
              :key="`game-${game.id}`" 
              class="bg-gray-800 shadow-md rounded-lg p-6 flex flex-col justify-between"
            >
              <h2 class="text-xl text-white font-semibold mb-2">
                Lobby {{ game.id }}: {{ game.game_type_name || 'Unknown Game' }}
              </h2>
              <p class="text-gray-300 mb-2">
                {{ playersCount[game.id] || 0 }} / {{ game.max_players || 'N/A' }} players
              </p>
              <p class="text-xs text-gray-400 mb-4">
                Status: {{ userInGames[game.id] ? 'You have joined' : 'Not joined' }}
              </p>

              <div class="flex justify-between text-center gap-2 md:gap-6 lg:gap-12">
                <Link
                  class="bg-green-900 hover:bg-green-800 text-green-200 font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
                  :href="route('room', { game: game.id, user: props.user.id })"
                >
                  Enter Game
                </Link>

                <button
                  @click="joinGame(game.id)"
                  :disabled="userInGames[game.id] || (playersCount[game.id] >= game.max_players) || isLoading"
                  class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  + Join Game
                </button>

                <button
                  @click="leaveGame(game.id)"
                  :disabled="!userInGames[game.id] || isLoading"
                  class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Leave
                </button>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else-if="!gamesError && !isLoading" class="text-gray-500 text-center mt-6">
            <p>No games available.</p>
            <p class="text-sm mt-2">Try adjusting your filters or check back later.</p>
          </div>

          <!-- Error State -->
          <div v-else-if="gamesError && !isLoading" class="text-red-500 text-center mt-6">
            <p>Error loading games. Please try again.</p>
            <button 
              @click="fetchGamesWithFilters" 
              class="mt-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded"
            >
              Retry
            </button>
          </div>

          <!-- Pagination Controls -->
          <div v-if="totalPages > 1 && !isLoading" class="mt-6">
            <DynamicPagination
              :currentPage="currentPage"
              :totalPages="totalPages"
              @change-page="changePage"
            />
          </div>
        </div>
      </div>
    </GameAuthenticatedLayout>
  </BreezeAuthenticatedLayout>
</template>