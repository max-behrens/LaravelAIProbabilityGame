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
const gamePlayersData = ref({}); // Store actual player data for each game
const userInGames = ref({});
const errorMessage = ref('');
const successMessage = ref('');
const isLoading = ref(false);
const playersLoading = ref(new Set()); // Track which games are loading players
const playerDataCache = ref({}); // Cache with timestamps
const CACHE_DURATION = 30000; // 30 seconds cache

// NEW: Dropdown states
const dropdownStates = ref({}); // Track which dropdowns are open
const playersLoadedOnce = ref(new Set()); // Track which games have been loaded at least once

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

// Check if player data is cached and still valid
const isPlayerDataCached = (gameId) => {
  const cached = playerDataCache.value[gameId];
  if (!cached) return false;
  
  const now = Date.now();
  return (now - cached.timestamp) < CACHE_DURATION;
};

// NEW: Toggle dropdown and load players only when opened
const togglePlayerDropdown = async (gameId) => {
  const isCurrentlyOpen = dropdownStates.value[gameId];
  
  // Close dropdown if it's open
  if (isCurrentlyOpen) {
    dropdownStates.value[gameId] = false;
    return;
  }

  // Open dropdown and load players if needed
  dropdownStates.value[gameId] = true;
  
  // Only fetch if we haven't loaded this game before or cache is stale
  if (!playersLoadedOnce.value.has(gameId) || !isPlayerDataCached(gameId)) {
    await fetchGamePlayers(gameId, true);
    playersLoadedOnce.value.add(gameId);
  }
};

// Fetch players for a specific game with caching (SAME AS BEFORE)
const fetchGamePlayers = async (gameId, forceRefresh = false) => {
  // Check cache first unless force refresh
  if (!forceRefresh && isPlayerDataCached(gameId)) {
    const cached = playerDataCache.value[gameId];
    gamePlayersData.value[gameId] = cached.players;
    playersCount.value[gameId] = cached.players.length;
    userInGames.value[gameId] = cached.players.some(player => player.id === props.user?.id);
    return;
  }

  if (playersLoading.value.has(gameId)) {
    return; // Already loading
  }

  try {
    playersLoading.value.add(gameId);
    
    const response = await axios.get(`/api/games/${gameId}/players`);
    const players = response.data;
    
    // Update cache
    playerDataCache.value[gameId] = {
      players,
      timestamp: Date.now()
    };
    
    // Update the reactive data
    gamePlayersData.value[gameId] = players;
    playersCount.value[gameId] = players.length;
    userInGames.value[gameId] = players.some(player => player.id === props.user?.id);
    
    console.log(`Fetched ${players.length} players for game ${gameId}:`, players.map(p => p.name));
    
  } catch (error) {
    console.error(`Failed to fetch players for game ${gameId}:`, error);
    
    // If we have cached data, use it even if stale
    const cached = playerDataCache.value[gameId];
    if (cached) {
      gamePlayersData.value[gameId] = cached.players;
      playersCount.value[gameId] = cached.players.length;
      userInGames.value[gameId] = cached.players.some(player => player.id === props.user?.id);
      console.log(`Using stale cache for game ${gameId} due to error`);
    } else {
      // Set defaults on error with no cache
      gamePlayersData.value[gameId] = [];
      playersCount.value[gameId] = 0;
      userInGames.value[gameId] = false;
    }
  } finally {
    playersLoading.value.delete(gameId);
  }
};

// REMOVED: fetchAllGamePlayers function - no longer needed since we load on-demand

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

  // Initialize default values for each game
  gamesList.forEach(game => {
    if (game && game.id) {
      // Initialize with defaults if not already set
      if (!(game.id in playersCount.value)) {
        playersCount.value[game.id] = 0;
        userInGames.value[game.id] = false;
        gamePlayersData.value[game.id] = [];
        dropdownStates.value[game.id] = false; // NEW: Initialize dropdown state
      }
    }
  });
};

// UPDATED: Watch liveGames from composable but DON'T automatically fetch player data
watchEffect(async () => {
  if (liveGames.value && Array.isArray(liveGames.value)) {
    updateGameState(liveGames.value);
    // REMOVED: automatic player fetching - now done on-demand when dropdown is clicked
  }
});

// Use liveGames from the composable instead of games.value
const currentPageGames = computed(() => {
  return Array.isArray(liveGames.value) ? liveGames.value : [];
});

const totalPages = computed(() => {
  return paginationMeta.value?.lastPage || 1;
});

// Helper computed properties for better display
const getPlayerDisplayText = (game) => {
  const currentPlayers = playersCount.value[game.id] || 0;
  const maxPlayers = game.max_players || 'N/A';
  return `${currentPlayers} / ${maxPlayers} players`;
};

const getPlayerNames = (game) => {
  const players = gamePlayersData.value[game.id] || [];
  return players.map(player => player.name).join(', ') || 'No players';
};

const isGameFull = (game) => {
  const currentPlayers = playersCount.value[game.id] || 0;
  const maxPlayers = game.max_players;
  return maxPlayers && currentPlayers >= maxPlayers;
};

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
      // Update local state optimistically
      userInGames.value[gameId] = true;
      playersCount.value[gameId] = (playersCount.value[gameId] || 0) + 1;

      // Invalidate cache and refresh player data for this specific game only
      delete playerDataCache.value[gameId];
      
      // Only refresh if the dropdown was open and data was loaded
      if (dropdownStates.value[gameId] && playersLoadedOnce.value.has(gameId)) {
        await fetchGamePlayers(gameId, true);
      }

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
      // Update local state optimistically
      userInGames.value[gameId] = false;
      playersCount.value[gameId] = Math.max(0, (playersCount.value[gameId] || 1) - 1);

      // Invalidate cache and refresh player data for this specific game only
      delete playerDataCache.value[gameId];
      
      // Only refresh if the dropdown was open and data was loaded
      if (dropdownStates.value[gameId] && playersLoadedOnce.value.has(gameId)) {
        await fetchGamePlayers(gameId, true);
      }

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

// Cleanup event listener and timeouts
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
              <div>
                <h2 class="text-xl text-white font-semibold mb-2">
                  Lobby {{ game.id }}: {{ game.game_type_name || 'Unknown Game' }}
                </h2>
                

                <!-- Player Details Dropdown -->
                <div class="mb-3">
                  
                  <button 
                    @click="togglePlayerDropdown(game.id)"
                    class="flex items-center justify-between w-full text-left text-gray-400 text-sm font-medium mb-1 hover:text-gray-300 transition-colors"
                    :class="{ 'text-blue-400': dropdownStates[game.id] }"
                  >
                    <span>
                      Player Details 
                    </span>
                    <svg 
                      class="w-4 h-4 transition-transform duration-200" 
                      :class="{ 'rotate-180': dropdownStates[game.id] }"
                      fill="none" 
                      stroke="currentColor" 
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                  </button>
                  
                  <!-- Dropdown Content -->
                  <div 
                    v-if="dropdownStates[game.id]" 
                    class="bg-gray-700 rounded-md p-3 mt-2 border border-gray-600"
                  >

                  <p class="text-gray-300 mb-1">
                    {{ getPlayerDisplayText(game) }}
                  </p>
                  <div class="w-full bg-gray-700 rounded-full h-2 mb-2">
                    <div 
                      class="h-2 rounded-full transition-all duration-300"
                      :class="{
                        'bg-green-500': !isGameFull(game),
                        'bg-red-500': isGameFull(game)
                      }"
                      :style="{
                        width: game.max_players ? `${Math.min(100, (playersCount[game.id] || 0) / game.max_players * 100)}%` : '0%'
                      }"
                    ></div>
                  </div>
                  <div v-if="isGameFull(game)" class="text-red-400 text-sm font-medium">
                    Game is full
                  </div>
                    <!-- Loading State -->
                    <div v-if="playersLoading.has(game.id)" class="text-gray-500 text-xs italic flex items-center">
                      <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Loading players...
                    </div>
                    
                    <!-- Players List -->
                    <div v-else-if="gamePlayersData[game.id] && gamePlayersData[game.id].length > 0">
                      <div class="text-gray-300 text-sm space-y-1">
                        <div 
                          v-for="player in gamePlayersData[game.id]" 
                          :key="player.id"
                          class="flex items-center justify-between py-1 px-2 bg-gray-600 rounded text-xs"
                        >
                          <span>{{ player.name }}</span>
                          <span 
                            v-if="player.id === props.user?.id" 
                            class="text-green-400 font-medium"
                          >
                            (You)
                          </span>
                        </div>
                      </div>
                      
                      <!-- Your Status -->
                      <div class="mt-2 pt-2 border-t border-gray-600">
                        <p class="text-xs text-gray-400">
                          Status: 
                          <span :class="userInGames[game.id] ? 'text-green-400' : 'text-red-400'">
                            {{ userInGames[game.id] ? 'You have joined' : 'Not joined' }}
                          </span>
                        </p>
                      </div>
                    </div>
                    
                    <!-- No Players -->
                    <div v-else class="text-gray-500 text-xs italic">
                      No players in this game yet
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex justify-between text-center gap-2 md:gap-6 lg:gap-12">
                <Link
                  class="bg-green-900 hover:bg-green-800 text-green-200 font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
                  :href="route('room', { game: game.id, user: props.user.id })"
                >
                  Enter Game
                </Link>

                <button
                  @click="joinGame(game.id)"
                  :disabled="userInGames[game.id] || isGameFull(game) || isLoading"
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