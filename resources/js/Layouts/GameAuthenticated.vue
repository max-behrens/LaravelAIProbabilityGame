<script setup>
import { ref, onMounted, computed, watch, nextTick, onUnmounted } from 'vue';
import BreezeApplicationLogo from '@/Components/ApplicationLogo.vue';
import BreezeDropdownLink from '@/Components/DropdownLink.vue';
import { Link } from '@inertiajs/inertia-vue3';
import { CalendarDaysIcon, UserIcon, FilterIcon } from 'lucide-vue-next';
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import axios from 'axios';

// Props - Fixed to allow null values and provide better defaults
const props = defineProps({
  currentGameId: {
    type: [String, Number, null],
    required: false,
    default: null
  }
});

// State
const showingNavigation = ref(false);
const showLogoutModal = ref(false);
const showFiltersSection = ref(false);
const showDateModal = ref(false);
const isDark = ref(false);
const isLoading = ref(false);

// Filter states
const dateRange = ref([null, null]);
const activeUserIds = ref([]);
const allUsers = ref([]);
const userSearchTerm = ref('');
const andUsers = ref(false);
const excludeAI = ref(false);
const gameType = ref('');
// Get initial filter values from URL params
const getInitialFilters = () => {
  try {
    const urlParams = new URLSearchParams(window.location.search);

    // Date range
    const startDate = urlParams.get('start_date');
    const endDate = urlParams.get('end_date');

    if (startDate && endDate) {
      dateRange.value = [new Date(startDate), new Date(endDate)];
    } else {
      dateRange.value = getDefaultDateRange();
    }

    // User IDs
    const userIds = urlParams.get('user_ids');
    if (userIds) {
      activeUserIds.value = userIds.split(',')
        .map(id => parseInt(id))
        .filter(id => !isNaN(id));
    }

    // AND users
    andUsers.value = urlParams.get('and_users') === 'true';

    // Game Type
    gameType.value = urlParams.get('game_type') || '';


    excludeAI.value = urlParams.get('ai_excluded') === 'true';

  } catch (error) {
    console.error('Error parsing initial filters:', error);
    // Set safe defaults
    dateRange.value = getDefaultDateRange();
    activeUserIds.value = [];
    andUsers.value = false;
  }
};

const toggleNavigation = () => {
  showingNavigation.value = !showingNavigation.value;
};

const toggleFiltersSection = () => {
  showFiltersSection.value = !showFiltersSection.value;
};

// Theme management
const initializeTheme = () => {
  try {
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    isDark.value = savedTheme ? savedTheme === 'dark' : systemPrefersDark;
    applyTheme();
  } catch (error) {
    console.error('Error initializing theme:', error);
    isDark.value = true; // Default to dark
    applyTheme();
  }
};

const applyTheme = () => {
  try {
    if (isDark.value) {
      document.documentElement.classList.add('dark');
      localStorage.setItem('theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      localStorage.setItem('theme', 'light');
    }
  } catch (error) {
    console.error('Error applying theme:', error);
  }
};

const toggleTheme = () => {
  isDark.value = !isDark.value;
  applyTheme();
};

// Date filter functions
const getDefaultDateRange = () => {
  // const endDate = new Date();
  // const startDate = new Date();
  // startDate.setDate(endDate.getDate() - 7); // Default to last 7 days
  // return [startDate, endDate];
  return [null, null];
};

const datepickerModel = computed({
  get() {
    // If dateRange is "all time" ([null, null]), we want the date picker to
    // open showing a single day (today) for the user to start from.
    if (!dateRange.value || (!dateRange.value[0] && !dateRange.value[1])) {
      const today = new Date();
      return [today, today]; // Return a temporary range for the date picker's display
    }
    return dateRange.value;
  },
  set(newValue) {
    // This setter is called when the user selects a date range.
    // We call the existing handler to process the new value and update dateRange.
    handleDateSelection(newValue);
  }
});

const dateFilterTitle = computed(() => {
  if (!dateRange.value || !dateRange.value[0] || !dateRange.value[1]) {
    return 'All Time';
  }
  try {
    const start = dateRange.value[0].toLocaleDateString('en-GB');
    const end = dateRange.value[1].toLocaleDateString('en-GB');
    return `${start} - ${end}`;
  } catch (error) {
    console.error('Error formatting date:', error);
    return 'Invalid Date Range';
  }
});

const handleDateSelection = (modelData) => {
  try {
    if (modelData && Array.isArray(modelData) && modelData.length === 2) {
      const startDate = modelData[0] instanceof Date ? modelData[0] : new Date(modelData[0]);
      const endDate = modelData[1] instanceof Date ? modelData[1] : new Date(modelData[1]);

      if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
        dateRange.value = [startDate, endDate];
      } else {
        dateRange.value = [null, null];
      }
    } else {
      dateRange.value = [null, null];
    }
    showDateModal.value = false;
  } catch (error) {
    console.error('Error handling date selection:', error);
    dateRange.value = [null, null];
    showDateModal.value = false;
  }
};

const clearDateFilter = () => {
  dateRange.value = [null, null];
  showDateModal.value = false;
};

// User filter functions
const fetchUsers = async () => {
  if (isLoading.value) return;
  
  try {
    isLoading.value = true;
    const response = await axios.get(`/dashboard/users`);
    
    if (response.data && Array.isArray(response.data)) {
      allUsers.value = response.data;
    } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
      allUsers.value = response.data.data;
    } else {
      console.warn('Unexpected users response format:', response.data);
      allUsers.value = [];
    }
  } catch (error) {
    console.error('Failed to fetch users:', error);
    allUsers.value = [];
  } finally {
    isLoading.value = false;
  }
};

const selectUserFilter = (userId) => {
  if (!userId) return;
  
  try {
    const currentIndex = activeUserIds.value.indexOf(userId);
    if (currentIndex > -1) {
      activeUserIds.value.splice(currentIndex, 1);
    } else {
      activeUserIds.value.push(userId);
    }
  } catch (error) {
    console.error('Error selecting user filter:', error);
  }
};

const clearAllUserSelections = () => {
  activeUserIds.value = [];
};

const toggleAndUsers = () => {
  andUsers.value = !andUsers.value;
};

const userFilterTitle = computed(() => {
  try {
    if (activeUserIds.value.length === 0) {
      return 'All Users';
    } else if (activeUserIds.value.length === 1) {
      const selectedUser = allUsers.value.find(user => user && user.id === activeUserIds.value[0]);
      return selectedUser ? selectedUser.name : 'User Filter';
    } else {
      return `${activeUserIds.value.length} Users`;
    }
  } catch (error) {
    console.error('Error computing user filter title:', error);
    return 'User Filter Error';
  }
});

const filteredUsers = computed(() => {
  try {
    if (!allUsers.value || !Array.isArray(allUsers.value)) {
      return [];
    }

    if (!userSearchTerm.value || !userSearchTerm.value.trim()) {
      return allUsers.value.filter(user => user && user.id); // Filter out null/undefined users
    }

    const lowerCaseSearchTerm = userSearchTerm.value.toLowerCase().trim();
    return allUsers.value.filter(user => {
      if (!user || !user.id) return false;
      const nameMatch = user.name && user.name.toLowerCase().includes(lowerCaseSearchTerm);
      const emailMatch = user.email && user.email.toLowerCase().includes(lowerCaseSearchTerm);
      return nameMatch || emailMatch;
    });
  } catch (error) {
    console.error('Error filtering users:', error);
    return [];
  }
});

  const allGameTypes = computed(() => [
    { value: '1', label: 'Object Detection Game' },
    { value: '2', label: 'Game of Lies' },
    // Add more game types as needed
  ]);

// Check if user is in room - Fixed to properly handle null values
const isInRoom = computed(() => {
  const gameId = props.currentGameId;
  return gameId !== null && gameId !== undefined && gameId !== 0 && !isNaN(gameId);
});

// URL parameter management
const updateUrlParams = () => {
  try {
    const params = new URLSearchParams();

    // Date range
    if (dateRange.value && dateRange.value[0] && dateRange.value[1]) {
      const startDate = dateRange.value[0] instanceof Date ? dateRange.value[0] : new Date(dateRange.value[0]);
      const endDate = dateRange.value[1] instanceof Date ? dateRange.value[1] : new Date(dateRange.value[1]);

      if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
        params.set('start_date', startDate.toISOString().split('T')[0]);
        params.set('end_date', endDate.toISOString().split('T')[0]);
      }
    }

    // User IDs
    if (activeUserIds.value && Array.isArray(activeUserIds.value) && activeUserIds.value.length > 0) {
      const validUserIds = activeUserIds.value.filter(id => id !== null && id !== undefined && !isNaN(id));
      if (validUserIds.length > 0) {
        params.set('user_ids', validUserIds.join(','));
      }
    }

    // AND users
    if (andUsers.value) {
      params.set('and_users', 'true');
    }

    // Game Type
  if (gameType.value) { // This now correctly handles the '' case for "All"
    params.set('game_type', gameType.value);
  }

    // Exclude AI
    if (excludeAI.value) {
      params.set('ai_excluded', 'true');
    }

    // Update URL without reloading
    const newUrl = window.location.pathname + (params.toString() ? `?${params.toString()}` : '');
    window.history.pushState(null, '', newUrl);

    // Emit filter change event for components to listen to
    window.dispatchEvent(new CustomEvent('gameFiltersChanged', {
      detail: {
        dateRange: dateRange.value,
        userIds: activeUserIds.value,
        andUsers: andUsers.value,
        excludeAI: excludeAI.value,
        gameType: gameType.value,

      }
    }));
  } catch (error) {
    console.error('Error updating URL params:', error);
  }
};

const activeFiltersDisplay = computed(() => {
  try {
    const filters = [];
    
    // Date range filter
    if (dateRange.value && dateRange.value[0] && dateRange.value[1]) {
      const start = dateRange.value[0].toLocaleDateString('en-GB');
      const end = dateRange.value[1].toLocaleDateString('en-GB');
      filters.push({
        type: 'date',
        label: `Date: ${start} - ${end}`,
        icon: 'calendar'
      });
    }
    
    // User filters
    if (activeUserIds.value && activeUserIds.value.length > 0) {
      if (activeUserIds.value.length === 1) {
        const user = allUsers.value.find(u => u && u.id === activeUserIds.value[0]);
        filters.push({
          type: 'user',
          label: `User: ${user?.name || 'Unknown'}`,
          icon: 'user'
        });
      } else {
        const operator = andUsers.value ? 'AND' : 'OR';
        filters.push({
          type: 'user',
          label: `Users: ${activeUserIds.value.length} selected (${operator})`,
          icon: 'users'
        });
      }
    }
    
  if (gameType.value && gameType.value !== '') {
    const gameTypeLabel = allGameTypes.value.find(type => type.value === gameType.value)?.label || `Type ${gameType.value}`;
    filters.push({
      type: 'gameType',
      label: `Game Type: ${gameTypeLabel}`,
      icon: 'game'
    });
  }

  // AI Score Filter
  if (excludeAI.value) {
    filters.push({
      type: 'excludeAI',
      label: `AI Scores Excluded`,
      icon: 'ai'
    });
  }

    return filters;
  } catch (error) {
    console.error('Error computing active filters:', error);
    return [];
  }
});

const hasActiveFilters = computed(() => {
  return activeFiltersDisplay.value.length > 0;
});

// Safe access to user data
const currentUser = computed(() => {
  try {
    return window.$page?.props?.auth?.user || null;
  } catch (error) {
    console.error('Error accessing current user:', error);
    return null;
  }
});

const showGameTypeFilter = computed(() => {
  // Hide the AI filter on the AI game route, but show it on other dashboard pages.
  return window.location.pathname.includes('/dashboard') && !window.location.pathname.includes('/room');
});

const excludeAIFilter = computed(() => {
  // Hide the AI filter on the AI game route, but show it on other dashboard pages.
  return window.location.pathname.includes('/dashboard') && !window.location.pathname.includes('/aigame');
});

// Watch for filter changes
watch([dateRange, activeUserIds, andUsers, excludeAI, gameType], () => {
  updateUrlParams();
}, { deep: true });

onMounted(async () => {
  try {
    initializeTheme();
    getInitialFilters();
    await fetchUsers();
  } catch (error) {
    console.error('Error during component mount:', error);
  }
});

onUnmounted(() => {
  // Clean up any event listeners or timers if needed
});
</script>

<template>
  <div class="bg-gray-900 text-white">
    <div class="min-h-screen bg-gray-900">
      <main class="flex min-h-screen">
        <transition name="slide">
          <nav
            v-if="showingNavigation"
            class="fixed top-0 left-0 h-full w-80 bg-gray-800 border-r border-gray-700 z-50 shadow-lg flex-shrink-0 overflow-y-auto"
          >
            <div class="flex flex-col h-full">
              <div class="flex-1 px-4 py-6 space-y-2 mt-16">
                <Link
                  :href="route('ai-game')"
                  class="flex items-center px-3 py-2 text-sm font-medium text-white hover:text-gray-300 hover:bg-gray-700 rounded-md transition duration-150 ease-in-out"
                  @click="showingNavigation = false"
                >
                  <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                  </svg>
                  AI Game
                </Link>

                <Link
                                    :href="isInRoom ? route('room', { game: props.currentGameId, user: $page.props.auth.user.id }) : '#'"
                  :class="{
                    'flex items-center px-3 py-2 text-sm font-medium transition duration-150 ease-in-out rounded-md': true,
                    'text-white hover:text-gray-300 hover:bg-gray-700': isInRoom,
                    'text-gray-500 cursor-not-allowed bg-gray-800': !isInRoom
                  }"
                  :disabled="!isInRoom"
                  @click="isInRoom && (showingNavigation = false)"
                >
                  <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                  Room
                </Link>

                <div class="pt-4 border-t border-gray-700">
                  <button
                    @click="toggleFiltersSection"
                    class="flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-white hover:text-gray-300 hover:bg-gray-700 rounded-md transition duration-150 ease-in-out"
                  >
                    <div class="flex items-center">
                      <FilterIcon class="mr-3 h-5 w-5" />
                      Game Filters
                    <svg
                      class="h-4 w-4 ml-6 transition-transform duration-200"
                      :class="{ 'rotate-180': showFiltersSection }"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    </div>
                  </button>

                  <transition name="filter-expand">
                    <div v-if="showFiltersSection" class="mt-2 ml-8 space-y-3">
                      <div>
                        <label class="block text-xs font-medium text-gray-400 mb-2">Date Range</label>
                        <button
                          @click="showDateModal = true"
                          class="flex items-center justify-between w-full px-3 py-2 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded-md transition duration-150 ease-in-out"
                        >
                          <div class="flex items-center">
                            <CalendarDaysIcon class="mr-2 h-4 w-4" />
                            <span class="truncate">{{ dateFilterTitle }}</span>
                          </div>
                        </button>
                        <button
                          v-if="dateRange && dateRange[0] && dateRange[1]"
                          @click="clearDateFilter"
                          class="w-full mt-1 px-2 py-1 text-xs bg-red-600 hover:bg-red-700 text-white rounded-md transition duration-150 ease-in-out"
                        >
                          Clear Date Filter
                        </button>
                      </div>

                      <div>
                        <div class="flex items-center justify-between mb-2">
                          <label class="block text-xs font-medium text-gray-400">Users</label>
                          <div class="flex space-x-1">
                            <button
                              v-if="activeUserIds.length > 0"
                              @click="toggleAndUsers"
                              :class="[
                                'px-2 py-1 text-xs rounded-md transition-colors',
                                andUsers ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700'
                              ]"
                            >
                              {{ andUsers ? 'AND' : 'OR' }}
                            </button>
                            <button
                              v-if="activeUserIds.length > 0"
                              @click="clearAllUserSelections"
                              class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-xs rounded-md transition-colors"
                            >
                              Clear ({{ activeUserIds.length }})
                            </button>
                          </div>
                        </div>

                        <input
                          type="text"
                          v-model="userSearchTerm"
                          placeholder="Search users..."
                          class="w-full p-2 mb-2 text-xs rounded-md bg-gray-700 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500"
                        />

                        <div v-if="activeUserIds.length > 0" class="flex flex-wrap gap-1 p-2 bg-gray-700 rounded-md mb-2">
                          <span
                            v-for="userId in activeUserIds"
                            :key="userId"
                            class="inline-flex items-center px-2 py-1 bg-teal-600 text-white text-xs rounded-md"
                          >
                            {{ allUsers.find(u => u && u.id === userId)?.name || 'Unknown' }}
                            <button
                              @click="selectUserFilter(userId)"
                              class="ml-1 text-teal-200 hover:text-white font-bold"
                            >
                              ×
                            </button>
                          </span>
                        </div>

                        <div class="max-h-40 overflow-y-auto custom-scrollbar rounded-md">
                          <button
                            v-for="user in filteredUsers"
                            :key="`user-${user.id}`"
                            :class="[
                              'flex items-center justify-between w-full px-2 py-1.5 text-xs rounded-md mb-1 text-white cursor-pointer',
                              'transition-all duration-100 ease-in-out hover:brightness-110',
                              'bg-teal-600/50',
                              activeUserIds.includes(user.id) ? 'bg-teal-700 ring-1 ring-teal-400' : ''
                            ]"
                            @click="selectUserFilter(user.id)"
                          >
                            <div class="flex items-center">
                              <UserIcon class="w-3 h-3 mr-2" />
                              <span class="truncate">{{ user.name || 'Unknown User' }}</span>
                            </div>
                            <span v-if="activeUserIds.includes(user.id)" class="text-teal-200 font-bold">✓</span>
                          </button>

                          <p v-if="filteredUsers.length === 0 && userSearchTerm.trim()" class="text-gray-400 text-center text-xs py-2">
                            No users found
                          </p>
                          <p v-else-if="allUsers.length === 0 && !isLoading" class="text-gray-400 text-center text-xs py-2">
                            No users available
                          </p>
                          <p v-else-if="isLoading" class="text-gray-400 text-center text-xs py-2">
                            Loading users...
                          </p>
                        </div>
                      </div>

                      <div v-if="showGameTypeFilter">
                        <label class="block text-xs font-medium text-gray-400 mb-2">Game Type</label>
                        <select
                          v-model="gameType"
                          class="w-full p-2 text-xs rounded-md bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-teal-500"
                        >
                          <option value="" selected>All Game Types</option>
                          <option v-for="type in allGameTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                          </option>
                        </select>
                      </div>
                        <div v-if="excludeAIFilter">
                          <label for="excludeAIToggle" class="block text-xs font-medium text-gray-400 mb-2">Show AI Scores</label>
                          <div class="relative flex items-center justify-between p-2 rounded-md bg-gray-700">
                            <span class="text-white text-sm">Exclude AI Scores</span>
                            <input
                              type="checkbox"
                              id="excludeAIToggle"
                              v-model="excludeAI"
                              class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500"
                            />
                          </div>
                      </div>
                    </div>
                  </transition>
                </div>
              </div>

              <div class="border-t border-gray-700 p-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <div class="flex-shrink-0">
                      <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center">
                        <span class="text-sm font-medium text-white">
                          {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                        </span>
                      </div>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-white">{{ $page.props.auth.user.name }}</p>
                      <p class="text-xs text-gray-400">{{ $page.props.auth.user.email }}</p>
                    </div>
                  <button
                    @click="showLogoutModal = true"
                    class="p-1 ml-6 rounded-md text-gray-400 hover:text-white hover:bg-gray-700"
                  >
                    <svg
                      class="h-4 w-4 text-gray-400"
                      xmlns="http://www.w3.org/2000/svg"
                      fill="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path d="M19.14 12.94a7.002 7.002 0 000-1.88l2.03-1.58a.5.5 0 00.12-.64l-1.92-3.32a.5.5 0 00-.6-.22l-2.39.96a6.978 6.978 0 00-1.6-.94l-.36-2.54A.5.5 0 0014 3h-4a.5.5 0 00-.49.42l-.36 2.54a6.978 6.978 0 00-1.6.94l-2.39-.96a.5.5 0 00-.6.22l-1.92 3.32a.5.5 0 00.12.64l2.03 1.58a7.002 7.002 0 000 1.88l-2.03 1.58a.5.5 0 00-.12.64l1.92 3.32a.5.5 0 00.6.22l2.39-.96c.5.38 1.04.7 1.6.94l.36 2.54A.5.5 0 0010 21h4a.5.5 0 00.49-.42l.36-2.54c.56-.24 1.1-.56 1.6-.94l2.39.96a.5.5 0 00.6-.22l1.92-3.32a.5.5 0 00-.12-.64l-2.03-1.58zM12 15.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z" />
                    </svg>
                  </button>
                  </div>
                </div>
              </div>
            </div>
          </nav>
        </transition>

        <div class="flex-1 pt-4">
          <div class="flex items-center mb-4">
            <button
              @click="toggleNavigation"
              class="game-menu-width inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:bg-gray-700 focus:text-white transition duration-150 ease-in-out"
            >
              <svg class="h-6 w-6 mr-2" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path
                  :class="{ hidden: showingNavigation, 'inline-flex': !showingNavigation }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"
                />
                <path
                  :class="{ hidden: !showingNavigation, 'inline-flex': showingNavigation }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
              Game Menu
            </button>

            <!-- Active Filters Display -->
            <div v-if="hasActiveFilters" class="flex items-center space-x-2 mr-4 ml-10">
              <span class="text-gray-400 text-sm font-medium">Active Filters:</span>
              <div class="flex flex-wrap gap-2">
                <div
                  v-for="filter in activeFiltersDisplay"
                  :key="`${filter.type}-${filter.label}`"
                  class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-teal-600/20 text-teal-300 border border-teal-600/30"
                >
                  <!-- Calendar Icon -->
                  <svg
                    v-if="filter.icon === 'calendar'"
                    class="w-3 h-3 mr-1.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>
                  <!-- Single User Icon -->
                  <svg
                    v-else-if="filter.icon === 'user'"
                    class="w-3 h-3 mr-1.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                    />
                  </svg>
                  <!-- Multiple Users Icon -->
                  <svg
                    v-else-if="filter.icon === 'users'"
                    class="w-3 h-3 mr-1.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a4 4 0 11-8 0 4 4 0 018 0z"
                    />
                  </svg>
                  
                  <span>{{ filter.label }}</span>
                </div>
              </div>
            </div>
          </div>

          <div><slot /></div>
        </div>

        <transition name="overlay">
          <div
            v-if="showingNavigation"
            class="fixed inset-0 bg-black bg-opacity-50 z-40"
            @click="showingNavigation = false"
          ></div>
        </transition>
      </main>

      <transition name="fade">
        <div
          v-if="showLogoutModal"
          class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50"
          @click.self="showLogoutModal = false"
        >
          <div class="bg-gray-900 rounded-lg shadow-lg p-6 w-96 max-w-full text-gray-200">
            <h2 class="text-lg font-semibold mb-4">User Options</h2>

            <div class="flex flex-col space-y-4">
              <button
                type="button"
                class="w-full px-4 py-2 rounded bg-gray-700 hover:bg-gray-600 text-left text-white flex items-center justify-between"
                @click="toggleTheme"
              >
                <span>{{ isDark ? 'Light Mode' : 'Dark Mode' }}</span>
                <svg
                  class="h-5 w-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  v-if="isDark"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <svg
                  class="h-5 w-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  v-else
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                </svg>
              </button>

              <button
                type="button"
                class="w-full px-4 py-2 rounded bg-gray-700 hover:bg-gray-600 text-left transition duration-150 ease-in-out"
                @click="showLogoutModal = false"
              >
                Settings
              </button>

              <BreezeDropdownLink
                :href="route('logout')"
                method="post"
                as="button"
                class="w-full px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white transition duration-150 ease-in-out"
                @click="showLogoutModal = false"
              >
                Log Out
              </BreezeDropdownLink>
            </div>
          </div>
        </div>
      </transition>

      <transition name="fade">
        <div
          v-if="showDateModal"
          class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
          @click.self="showDateModal = false"
        >
          <div class="bg-gray-800 rounded-lg shadow-xl p-4 relative max-w-md w-full min-h-[500px] flex flex-col">
            <h3 class="text-xl font-semibold text-white mb-4">Select Date Range</h3>

            <button
              @click="showDateModal = false"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-200"
              aria-label="Close"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>

            <div class="flex-1 mb-4">
              <Datepicker
                ref="datepickerRef"
                v-model="datepickerModel"
                range
                :enable-time-picker="false"
                :dark="true"
                :teleport="false"
                placeholder="Select Date Range"
                :min-date="new Date('2024-01-01')"
                :max-date="new Date()"
                class="my-4"
                @update:model-value="handleDateSelection"
              />
            </div>

            <div class="flex justify-end space-x-2 mt-auto">
              <button
                @click="clearDateFilter"
                class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md"
              >
                Clear
              </button>
              <button
                @click="showDateModal = false"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<style>
.dp__menu {
  z-index: 9999 !important;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.overlay-enter-active,
.overlay-leave-active {
  transition: opacity 0.3s ease;
}
.overlay-enter-from,
.overlay-leave-to {
  opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(-100%);
}

.filter-expand-enter-active,
.filter-expand-leave-active {
  transition: all 0.3s ease;
}
.filter-expand-enter-from,
.filter-expand-leave-to {
  opacity: 0;
  max-height: 0;
  transform: translateY(-10px);
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: #374151;
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #6b7280;
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

@media (max-width: 768px) {
  .flex.items-center.justify-between {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }
  
  .inline-flex.items-center.px-3.py-1 {
    font-size: 0.75rem;
    padding: 0.25rem 0.75rem;
  }
  
  .max-w-48 {
    max-width: 8rem;
  }
}

/* Ensure filter chips don't break awkwardly */
.flex.flex-wrap.gap-2 {
  max-width: calc(100vw - 16rem);
}

/* Animation for filter appearance */
.inline-flex.items-center.px-3.py-1 {
  transition: all 0.2s ease-in-out;
  animation: filterFadeIn 0.3s ease-out;
}

@keyframes filterFadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>