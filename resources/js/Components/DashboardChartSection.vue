<script setup>
import { ChartBarIcon, PuzzleIcon, Gamepad2Icon, ClockIcon, TrendingUpIcon, EyeIcon, CalendarDaysIcon, ScalingIcon, UserIcon, ChevronLeftIcon, ChevronRightIcon, BrainCircuitIcon, LineChartIcon, ChartColumnStacked } from 'lucide-vue-next';
import DashboardHeatmapComponent from '@/Components/DashboardHeatmapComponent.vue';
import DashboardBarChartComponent from '@/Components/DashboardBarChartComponent.vue';
import DashboardLineChartComponent from '@/Components/DashboardLineChartComponent.vue';
import { Head, router, usePage } from '@inertiajs/inertia-vue3';
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import Datepicker from '@vuepic/vue-datepicker';

const page = usePage();
const initialGameId = page.props.current_game_id || null;
const initialStartDate = page.props.current_start_date ? new Date(page.props.current_start_date) : null;
const initialEndDate = page.props.current_end_date ? new Date(page.props.current_end_date) : null;
const initialExponentialScale = page.props.current_exponential_scale === 'true';
const initialUserId = page.props.current_user_id || null;

const props = defineProps({
  difficulties: {
      type: Array,
      default: () => []
  },
  categories: {
      type: Array,
      default: () => []
  }
});

const showAdvancedFilters = ref(false);

const getDefaultDateRange = () => {
  const endDate = new Date(); // Today
  const startDate = new Date();
  startDate.setDate(endDate.getDate() - 7);
  return [startDate, endDate];
};

// Chart navigation state
const currentChartIndex = ref(0);
const chartConfigs = [
  {
    title: 'Scores Per Game',
    description: 'Compare single & multi player statistics',
    component: 'DashboardHeatmapComponent',
    icon: ChartColumnStacked
  },  
  {
    title: 'Scores Over Time',
    description: 'Observe trends in player progress over time',
    component: 'DashboardLineChartComponent',
    icon: LineChartIcon
  },
];

// **FIXED**: AI Feature States - Default to true (ON)
const showAiScores = ref(true); // Changed default to true
const andUsers = ref(false);
const difficultyId = ref(null);
const categoryId = ref(null);
const showAiTooltip = ref(false);
const currentChartPointAiData = ref(null);

// Computed properties for current chart
const currentChart = computed(() => chartConfigs[currentChartIndex.value]);

// Chart toggle function
const toggleChart = () => {
  currentChartIndex.value = (currentChartIndex.value + 1) % chartConfigs.length;
};

// Filter States
const activeGameId = ref(initialGameId);
const showDateModal = ref(false);

// Keep dateRange as originally intended: empty if no URL params
const dateRange = ref(
  initialStartDate && initialEndDate
    ? [initialStartDate, initialEndDate]
    : getDefaultDateRange()
);

// Computed property for the Datepicker's v-model
const datepickerModel = computed({
    get() {
        if (!dateRange.value[0] && !dateRange.value[1]) {
            return [new Date(), new Date()];
        }
        return dateRange.value;
    },
    set(newValue) {
        handleDateSelection(newValue);
    }
});

onMounted(async () => {
  // **FIXED**: Initialize showAiScores from URL param, but default to true if not set
  showAiScores.value = page.props.current_show_ai_scores === 'true';  
  andUsers.value = page.props.current_and_users === 'true';  

  difficultyId.value = page.props.current_difficulty_id ? parseInt(page.props.current_difficulty_id) : null;
  categoryId.value = page.props.current_category_id ? parseInt(page.props.current_category_id) : null;

  // Add event listener for chart switching from hero component
  const handleChartSwitch = (event) => {
    console.log('Chart switch event received:', event.detail);
    if (event.detail && typeof event.detail.chartIndex === 'number') {
      currentChartIndex.value = event.detail.chartIndex;
    }
  };
  
  document.addEventListener('switchChart', handleChartSwitch);
  
  // Store the handler reference for cleanup
  window.chartSwitchHandler = handleChartSwitch;
  
  // Fetch users on mount
  await fetchUsers();
});

const isExponentialScale = ref(initialExponentialScale);

// User Filter States
const userSearchTerm = ref('');
const allUsers = ref([]);
const userFilterColor = 'bg-teal-600/50';

// Map selectedGame index to actual game IDs and names
const gameFilters = [
  { id: 1, name: 'Object Detection Game' },
  { id: 2, name: 'Fake or Steal' }
];

const gameIcons = [PuzzleIcon, Gamepad2Icon, ChartBarIcon];
const performanceIcons = [ClockIcon, TrendingUpIcon, EyeIcon];
const UserIcons = [UserIcon, UserIcon, UserIcon];

// Adjusted colors for better active state visibility
const optionColors = ['bg-teal-600/50', 'bg-blue-600/50'];
const dateFilterColor = 'bg-orange-600/50';
const performanceFilterColor = 'bg-purple-600/50';
const aiFilterColor = 'bg-lime-600/50';
const chartToggleColor = 'bg-indigo-600/50';

// Computed property for the dynamic game filter title
const gameFilterTitle = computed(() => {
  if (activeGameId.value === null) {
    return 'Game Filter - All Games';
  }
  const selectedFilter = gameFilters.find(filter => filter.id === activeGameId.value);
  return selectedFilter ? `Game Filter - ${selectedFilter.name}` : 'Game Filter';
});

// Computed property for the dynamic date filter title
const dateFilterTitle = computed(() => {
  if (!dateRange.value || !dateRange.value[0] || !dateRange.value[1]) {
    return 'All Time';
  }
  const start = dateRange.value[0].toLocaleDateString('en-GB');
  const end = dateRange.value[1].toLocaleDateString('en-GB');
  return `${start} - ${end}`;
});

// --- Filter Action Functions ---

const activeUserIds = ref(initialUserId ? [initialUserId] : []);


// Function to handle game filter button click
const selectGameFilter = (gameId) => {
  if (activeGameId.value === gameId) {
    activeGameId.value = null; // Deselect
  } else {
    activeGameId.value = gameId; // Select
  }
};

// Function to handle user filter button click
const selectUserFilter = (userId) => {
  const currentIndex = activeUserIds.value.indexOf(userId);
  if (currentIndex > -1) {
    // User is already selected, remove them
    activeUserIds.value.splice(currentIndex, 1);
  } else {
    // User is not selected, add them
    activeUserIds.value.push(userId);
  }
};


// Computed property for the dynamic user filter title
const userFilterTitle = computed(() => {
  if (activeUserIds.value.length === 0) {
    return 'User Filter - All Users';
  } else if (activeUserIds.value.length === 1) {
    const selectedUser = allUsers.value.find(user => user.id === activeUserIds.value[0]);
    return selectedUser ? `User Filter - ${selectedUser.name}` : 'User Filter';
  } else {
    return `User Filter - ${activeUserIds.value.length} Users Selected`;
  }
});


const clearAllUserSelections = () => {
  activeUserIds.value = [];
};

// Function to toggle exponential scale
const toggleExponentialScale = () => {
  isExponentialScale.value = !isExponentialScale.value;
};

// **FIXED**: Function to toggle AI scores display
const toggleAiScores = () => {
  showAiScores.value = !showAiScores.value;
  // If we hide AI scores, also hide the tooltip
  if (!showAiScores.value) {
    showAiTooltip.value = false;
    currentChartPointAiData.value = null;
  }
};

const toggleAndUsers = () => {
  andUsers.value = !andUsers.value;
};



// Function to handle chart point click and show AI tooltip
const handleChartPointClick = (data) => {
  // Only show tooltip if AI scores are enabled
  if (showAiScores.value && data && data.ai_score !== undefined) {
    currentChartPointAiData.value = data;
    showAiTooltip.value = true;
  } else {
    showAiTooltip.value = false;
    currentChartPointAiData.value = null;
  }
};

// Function to clear date filter
const clearDateFilter = () => {
  dateRange.value = [null, null]; // This truly clears the filter
  showDateModal.value = false;
};

const handleDateSelection = (modelData) => {
  if (modelData && Array.isArray(modelData) && modelData.length === 2) {
    const startDate = modelData[0] instanceof Date ? modelData[0] : new Date(modelData[0]);
    const endDate = modelData[1] instanceof Date ? modelData[1] : new Date(modelData[1]);

    if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
      dateRange.value = [startDate, endDate];
    } else {
      console.error('Invalid date selection:', modelData);
      dateRange.value = [null, null]; // Set to nulls if invalid selection
    }
  } else {
    dateRange.value = [null, null];
  }
  showDateModal.value = false;
};

const fetchUsers = async () => {
  try {
    const response = await axios.get(`/dashboard/users`);
    if (response.data && Array.isArray(response.data)) {
      allUsers.value = response.data;
    } else if (response.data && response.data.data && Array.isArray(response.data.data)) {
      allUsers.value = response.data.data;
    } else {
      console.error('Unexpected user data structure:', response.data);
      allUsers.value = [];
    }
  } catch (error) {
    console.error('Failed to fetch users:', error);
    allUsers.value = [];
  }
};

const filteredUsers = computed(() => {
  if (!allUsers.value || !Array.isArray(allUsers.value)) {
    return [];
  }

  if (!userSearchTerm.value || !userSearchTerm.value.trim()) {
    return allUsers.value;
  }

  const lowerCaseSearchTerm = userSearchTerm.value.toLowerCase().trim();
  return allUsers.value.filter(user => {
    if (!user) return false;

    const nameMatch = user.name && user.name.toLowerCase().includes(lowerCaseSearchTerm);
    const emailMatch = user.email && user.email.toLowerCase().includes(lowerCaseSearchTerm);

    return nameMatch || emailMatch;
  });
});

const rotateDifficulty = () => {
    if (!props.difficulties || props.difficulties.length === 0) {
        return;
    }
    
    if (difficultyId.value === null) {
        // Start with the first difficulty
        difficultyId.value = props.difficulties[0].id;
    } else {
        // Find current index and move to next
        const currentIndex = props.difficulties.findIndex(d => d.id === difficultyId.value);
        if (currentIndex === -1 || currentIndex === props.difficulties.length - 1) {
            // If not found or at the end, go back to "All" (null)
            difficultyId.value = null;
        } else {
            // Move to next difficulty
            difficultyId.value = props.difficulties[currentIndex + 1].id;
        }
    }
};

const rotateCategory = () => {
    if (!props.categories || props.categories.length === 0) {
        return;
    }
    
    if (categoryId.value === null) {
        // Start with the first category
        categoryId.value = props.categories[0].id;
    } else {
        // Find current index and move to next
        const currentIndex = props.categories.findIndex(c => c.id === categoryId.value);
        if (currentIndex === -1 || currentIndex === props.categories.length - 1) {
            // If not found or at the end, go back to "All" (null)
            categoryId.value = null;
        } else {
            // Move to next category
            categoryId.value = props.categories[currentIndex + 1].id;
        }
    }
};

const datepickerRef = ref(null);

watch(showDateModal, async (newVal) => {
  if (newVal) {
    await nextTick(); // wait for DOM to render
    if (datepickerRef.value && typeof datepickerRef.value.openMenu === 'function') {
      datepickerRef.value.openMenu();
    }
  }
});

watch(activeUserIds, (newIds, oldIds) => {
  console.log('activeUserIds changed from:', oldIds, 'to:', newIds);
}, { deep: true });

watch(
  [activeGameId, dateRange, isExponentialScale, activeUserIds, andUsers, difficultyId, categoryId, showAiScores],
  ([newGameId, newDateRange, newIsExponentialScale, newUserIds, newAndUsers, newDifficultyId, newCategoryId, newShowAiScores]) => {
    console.log('Watcher triggered with userIds:', newUserIds); // Debug log
    
    const params = new URLSearchParams();

    if (newGameId !== null) {
      params.set('game_id', newGameId);
    }

    if (newDateRange && newDateRange[0] && newDateRange[1]) {
      const startDate = newDateRange[0] instanceof Date ? newDateRange[0] : new Date(newDateRange[0]);
      const endDate = newDateRange[1] instanceof Date ? newDateRange[1] : new Date(newDateRange[1]);

      if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
        params.set('start_date', startDate.toISOString().split('T')[0]);
        params.set('end_date', endDate.toISOString().split('T')[0]);
      }
    }

    if (newIsExponentialScale) {
      params.set('exponential_scale', 'true');
    }

    console.log('USER IDS: ' + newUserIds);

    // FIXED: Ensure user IDs are properly handled
    if (newUserIds && Array.isArray(newUserIds) && newUserIds.length > 0) {
      // Filter out any null/undefined values and ensure they're numbers
      const validUserIds = newUserIds.filter(id => id !== null && id !== undefined && !isNaN(id));
      if (validUserIds.length > 0) {
        params.set('user_ids', validUserIds.join(','));
        console.log('Setting user_ids in URL:', validUserIds.join(',')); // Debug log
      }

    }

    if (newAndUsers) {
      params.set('and_users', 'true');
    }

    if (newDifficultyId) {
      params.set('difficulty_id', newDifficultyId);
    }

    if (newCategoryId) {
      params.set('category_id', newCategoryId);
    }


    if (newShowAiScores) {
      params.set('show_ai_scores', 'true');
    }

    // Construct new URL with updated query string
    const newRelativePathQuery = window.location.pathname + (params.toString() ? `?${params.toString()}` : '');

    console.log('New URL will be:', newRelativePathQuery); // Debug log

    // Use pushState to update the URL without reloading the page
    window.history.pushState(null, '', newRelativePathQuery);
  },
  { immediate: false, deep: true } // Added deep: true to watch array changes properly
);

// Close AI tooltip on outside click
const handleClickOutside = (event) => {
    if (showAiTooltip.value && !event.target.closest('.ai-tooltip') && !event.target.closest('.apexcharts-point')) {
        showAiTooltip.value = false;
        currentChartPointAiData.value = null;
    }
};

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    if (window.chartSwitchHandler) {
      document.removeEventListener('switchChart', window.chartSwitchHandler);
      delete window.chartSwitchHandler;
    }
});
</script>

<template>
 <section class="rounded-lg" 
         style="background: linear-gradient(to bottom, transparent 0%, rgb(31 41 55) 10%, rgb(31 41 55) 100%);">
  <div class="flex justify-center mx-auto px-20">
    <div class="lg:col-span-3 w-full">

                <!-- Mobile: Collapsible filters -->
                <details class="block lg:hidden mb-6 px-2">
                    <summary class="cursor-pointer select-none bg-gray-700 text-white px-4 py-2 rounded-lg">
                      Filters
                    </summary>
                    <div class="mt-2 space-y-2">
                      <!-- Chart Toggle - Full width on mobile -->
                      <div class="flex items-center gap-2 w-full">
                          <button
                              @click="toggleChart"
                              :class="[
                                  'flex items-center p-3 rounded-lg text-white cursor-pointer text-sm md:text-base h-full w-full',
                                  'transition-all duration-300 ease-in-out',
                                  chartToggleColor + ' hover:brightness-90'
                              ]"
                          >
                              <!-- Left - Title and Description -->
                              <div class="flex-1 text-left p-2">
                                  <div class="font-medium truncate">{{ currentChart.title }}</div>
                                  <div class="text-xs opacity-75 truncate text-wrap">{{ currentChart.description }}</div>
                              </div>
                              <!-- Right - Icon -->
                              <div class="flex-shrink-0 p-2">
                                  <component :is="currentChart.icon" class="w-8 h-8 lg:w-10 lg:h-10" />
                              </div>
                          </button>
                          
                          <button
                            @click="toggleChart"
                            :class="[
                                'flex justify-center items-center p-3 mt-0 lg:mt-2 rounded-lg shadow-md text-white cursor-pointer h-12 w-12',
                                'transition-all duration-300 ease-in-out hover:brightness-90',
                            ]"
                          >
                              <!-- Right Arrow Icon -->
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                  viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                              </svg>
                          </button>
                      </div>

                      <!-- Date Filter -->
                      <div class="mb-2 w-full">
                          <button
                              @click="showDateModal = true"
                              :class="[
                                  'flex items-center justify-center space-x-2 p-4 rounded-lg shadow-md text-white cursor-pointer text-sm md:text-base h-full w-full',
                                  'transition-all duration-300 ease-in-out',
                                  dateFilterColor + ' hover:brightness-90',
                                  (dateRange[0] && dateRange[1]) ? 'bg-orange-900/50 ring-2 ring-orange-400' : ''
                              ]"
                          >
                              <CalendarDaysIcon class="w-5 h-5 shrink-0" />
                              <span class="font-medium truncate">{{ dateFilterTitle }}</span>
                          </button>
                      </div>

                      <!-- Game Filters -->
                      <div class="flex flex-col space-y-2 flex-grow w-full">
                          <button
                              v-for="(filter, index) in gameFilters"
                              :key="filter.id"
                              :class="[
                                  'flex items-center justify-center space-x-2 p-3 rounded-lg shadow-md text-white cursor-pointer text-sm md:text-base',
                                  'transition-all duration-300 ease-in-out',
                                  optionColors[index] + ' hover:brightness-90',
                                  activeGameId === filter.id ? 'bg-blue-700 ring-2 ring-gray-400' : ''
                              ]"
                              @click="selectGameFilter(filter.id)"
                          >
                              <component :is="gameIcons[index]" class="w-5 h-5 shrink-0" />
                              <span class="font-medium truncate">{{ filter.name }}</span>
                          </button>
                      </div>

                      <!-- Difficulty and Category -->
                      <div class="flex flex-col space-y-2 w-full">
                          <!-- Difficulty Filter -->
                          <button
                              @click="rotateDifficulty"
                              :class="[
                                  'flex items-center justify-center space-x-2 p-3 rounded-lg shadow-md text-white cursor-pointer text-sm md:text-base',
                                  'transition-all duration-300 ease-in-out',
                                  'bg-green-600/50 hover:brightness-90',
                                  difficultyId ? 'bg-green-700 ring-2 ring-green-400' : ''
                              ]"
                          >
                              <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                              </svg>
                              <span class="font-medium truncate">
                                  {{ difficultyId ? difficulties.find(d => d.id === difficultyId)?.name : 'All Difficulties' }}
                              </span>
                          </button>

                          <!-- Category Filter -->
                          <button
                              @click="rotateCategory"
                              :class="[
                                  'flex items-center justify-center space-x-2 p-3 rounded-lg shadow-md text-white cursor-pointer text-sm md:text-base',
                                  'transition-all duration-300 ease-in-out',
                                  'bg-pink-600/50 hover:brightness-90',
                                  categoryId ? 'bg-pink-700 ring-2 ring-pink-400' : ''
                              ]"
                          >
                              <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                              </svg>
                              <span class="font-medium truncate">
                                  {{ categoryId ? categories.find(c => c.id === categoryId)?.name : 'All Categories' }}
                              </span>
                          </button>
                      </div>

                      <!-- AI Scores Toggle -->
                      <div class="mb-2 w-full">
                          <button
                              @click="toggleAiScores"
                              :class="[
                                  'flex items-center justify-center space-x-2 p-4 rounded-lg shadow-md text-white cursor-pointer text-sm md:text-base h-full w-full',
                                  'transition-all duration-300 ease-in-out',
                                  aiFilterColor + ' hover:brightness-90',
                                  showAiScores ? 'bg-lime-700 ring-2 ring-lime-400' : ''
                              ]"
                          >
                              <BrainCircuitIcon class="w-5 h-5 shrink-0" />
                              <span class="font-medium truncate">{{ showAiScores ? 'AI Scores ON' : 'AI Scores OFF' }}</span>
                          </button>
                      </div>

                      <!-- Advanced Filters Toggle -->
                      <div class="mb-2 w-full">
                          <button
                              @click="showAdvancedFilters = !showAdvancedFilters"
                              :class="[
                                  'w-full flex items-center justify-center space-x-2 p-4 rounded-lg shadow-md text-white cursor-pointer text-sm md:text-base h-full',
                                  'transition-all duration-300 ease-in-out',
                                  'bg-gray-600/50 hover:bg-gray-700',
                                  showAdvancedFilters ? 'bg-gray-700' : ''
                              ]"
                          >
                               <span class="text-xl shrink-0">
                                   <template v-if="showAdvancedFilters">
                                       <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-up"><path d="m18 15-6-6-6 6"/></svg>
                                   </template>
                                   <template v-else>
                                       <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down"><path d="m6 9 6 6 6-6"/></svg>
                                   </template>
                               </span>
                              <span class="font-medium truncate px-1">Advanced</span>
                          </button>
                      </div>
                    </div>
                  </details>

                <!-- Desktop: Two-column layout -->
                <div class="hidden lg:grid lg:grid-cols-12 lg:gap-6 mb-6">
                    <!-- Chart Section - Takes up most of the space (8/12 columns) -->
                    <div class="lg:col-span-8">
                        <div class="space-y-8 p-2 lg:p-6 rounded-lg relative">
                            <section>
                                <div class="w-full">
                                    <transition name="chart-fade" mode="out-in">
                                        <div :key="currentChartIndex" class="w-full">
                                            <DashboardLineChartComponent
                                                v-if="currentChart.component === 'DashboardLineChartComponent'"
                                                :game-type-id="activeGameId"
                                                :start-date="dateRange[0]"
                                                :end-date="dateRange[1]"
                                                :is-exponential-scale="isExponentialScale"
                                                :user-ids="activeUserIds"
                                                :difficulty-id="difficultyId"
                                                :category-id="categoryId"
                                                :show-ai-scores="showAiScores" 
                                                @pointClicked="handleChartPointClick"
                                                class="w-full" />
                                            <DashboardHeatmapComponent
                                                v-else-if="currentChart.component === 'DashboardHeatmapComponent'"
                                                :game-type-id="activeGameId"
                                                :start-date="dateRange[0]"
                                                :end-date="dateRange[1]"
                                                :user-ids="activeUserIds"
                                                :and-users="andUsers"
                                                :difficulty-id="difficultyId"
                                                :category-id="categoryId"
                                                :show-ai-scores="showAiScores"
                                                class="w-full"
                                            />
                                        </div>
                                    </transition>
                                </div>
                            </section>
                        </div>
                    </div>

                    <!-- Filters Sidebar - Takes up remaining space (4/12 columns) -->
                    <div class="lg:col-span-4 mt-4">
                        <div class="bg-gray-700 rounded-lg p-4 space-y-4 sticky top-4">
                            <!-- Chart Toggle -->
                            <div class="space-y-2">
                                <h3 class="text-white font-semibold text-sm mb-2">Chart Type</h3>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="toggleChart"
                                        :class="[
                                            'flex items-center p-3 rounded-lg text-white cursor-pointer text-sm w-full',
                                            'transition-all duration-300 ease-in-out',
                                            chartToggleColor + ' hover:brightness-90'
                                        ]"
                                    >
                                        <div class="flex-1 text-left">
                                            <div class="font-medium text-sm">{{ currentChart.title }}</div>
                                            <div class="text-xs opacity-75">{{ currentChart.description }}</div>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <component :is="currentChart.icon" class="w-6 h-6" />
                                        </div>
                                    </button>
                                    
                                    <button
                                      @click="toggleChart"
                                      :class="[
                                          'flex justify-center items-center p-2 rounded-lg shadow-md text-white cursor-pointer h-10 w-10',
                                          'transition-all duration-300 ease-in-out hover:brightness-90 bg-gray-600',
                                      ]"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Date Filter -->
                            <div class="space-y-2">
                                <h3 class="text-white font-semibold text-sm mb-2">Date Range</h3>
                                <button
                                    @click="showDateModal = true"
                                    :class="[
                                        'flex items-center space-x-2 p-3 rounded-lg text-white cursor-pointer text-sm w-full',
                                        'transition-all duration-300 ease-in-out',
                                        dateFilterColor + ' hover:brightness-90',
                                        (dateRange[0] && dateRange[1]) ? 'bg-orange-900/50 ring-2 ring-orange-400' : ''
                                    ]"
                                >
                                    <CalendarDaysIcon class="w-5 h-5 shrink-0" />
                                    <span class="font-medium">{{ dateFilterTitle }}</span>
                                </button>
                            </div>

                            <!-- Game Filters -->
                            <div class="space-y-2">
                              <h3 class="text-white font-semibold text-sm mb-2">Games</h3>
                              <div class="w-full flex space-x-2">
                                <button
                                  v-for="(filter, index) in gameFilters"
                                  :key="filter.id"
                                  :class="[
                                    'flex items-center space-x-2 p-3 rounded-lg text-white cursor-pointer text-sm w-1/2',
                                    'transition-all duration-300 ease-in-out',
                                    activeGameId === filter.id
                                      ? optionColors[index].replace('/50', '') + ' ring-2 ring-gray-400'
                                      : optionColors[index],
                                    'hover:brightness-90'
                                  ]"
                                  @click="selectGameFilter(filter.id)"
                                >
                                  <component :is="gameIcons[index]" class="w-5 h-5 shrink-0" />
                                  <span class="font-medium">{{ filter.name }}</span>
                                </button>
                              </div>
                            </div>

                            <!-- Difficulty and Category -->
                            <div class="space-y-2">
                              <h3 class="text-white font-semibold text-sm mb-2">Game Filters</h3>
                              <div class="flex space-x-2">
                                <!-- Difficulty Filter -->
                                <button
                                  @click="rotateDifficulty"
                                  :class="[
                                    'flex items-center space-x-2 p-3 rounded-lg text-white cursor-pointer text-sm flex-1',
                                    'transition-all duration-300 ease-in-out',
                                    'bg-green-600/50 hover:brightness-90',
                                    difficultyId ? 'bg-green-700 ring-2 ring-green-400' : ''
                                  ]"
                                >
                                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                  </svg>
                                  <span class="font-medium">
                                    {{ difficultyId ? difficulties.find(d => d.id === difficultyId)?.name : 'All Difficulties' }}
                                  </span>
                                </button>

                                <!-- Category Filter -->
                                <button
                                  @click="rotateCategory"
                                  :class="[
                                    'flex items-center space-x-2 p-3 rounded-lg text-white cursor-pointer text-sm flex-1',
                                    'transition-all duration-300 ease-in-out',
                                    'bg-pink-600/50 hover:brightness-90',
                                    categoryId ? 'bg-pink-700 ring-2 ring-pink-400' : ''
                                  ]"
                                >
                                  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                  </svg>
                                  <span class="font-medium">
                                    {{ categoryId ? categories.find(c => c.id === categoryId)?.name : 'All Categories' }}
                                  </span>
                                </button>
                              </div>
                            </div>


                            <!-- AI Scores Toggle -->
                            <div class="space-y-2">
                                <h3 class="text-white font-semibold text-sm mb-2">AI Features</h3>
                                <button
                                    @click="toggleAiScores"
                                    :class="[
                                        'flex items-center space-x-2 p-3 rounded-lg text-white cursor-pointer text-sm w-full',
                                        'transition-all duration-300 ease-in-out',
                                        aiFilterColor + ' hover:brightness-90',
                                        showAiScores ? 'bg-lime-700 ring-2 ring-lime-400' : ''
                                    ]"
                                >
                                    <BrainCircuitIcon class="w-5 h-5 shrink-0" />
                                    <span class="font-medium">{{ showAiScores ? 'AI Scores ON' : 'AI Scores OFF' }}</span>
                                </button>
                            </div>

                            <!-- Advanced Filters Toggle -->
                            <div class="space-y-2">
                                <button
                                    @click="showAdvancedFilters = !showAdvancedFilters"
                                    :class="[
                                        'w-full flex items-center space-x-2 p-3 rounded-lg text-white cursor-pointer text-sm',
                                        'transition-all duration-300 ease-in-out',
                                        'bg-gray-600/50 hover:bg-gray-700',
                                        showAdvancedFilters ? 'bg-gray-700' : ''
                                    ]"
                                >
                                     <span class="text-xl shrink-0">
                                         <template v-if="showAdvancedFilters">
                                             <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-up"><path d="m18 15-6-6-6 6"/></svg>
                                         </template>
                                         <template v-else>
                                             <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down"><path d="m6 9 6 6 6-6"/></svg>
                                         </template>
                                     </span>
                                    <span class="font-medium">Advanced</span>
                                </button>

                                <!-- Advanced Filters Section -->
                                <transition name="fade-slide-y">
                                    <div v-if="showAdvancedFilters" class="mt-4">
                                        <div class="bg-gray-800 p-4 rounded-lg shadow-md space-y-4">
                                            <!-- User Filter Section -->
                                            <div>
                                                <div class="flex flex-col justify-between mb-3 gap-2">
                                                    <h4 class="text-white font-semibold text-sm">{{ userFilterTitle }}</h4>
                                                    <div class="flex space-x-2">
                                                        <button
                                                          v-if="activeUserIds.length > 1"
                                                          @click="toggleAndUsers"
                                                          :class="[
                                                            'px-2 py-1 text-white text-xs rounded-md transition-colors',
                                                            andUsers ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700'
                                                          ]"
                                                        >
                                                          {{ andUsers ? 'AND Users' : 'OR Users' }}
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
                                                
                                                <div class="space-y-2">
                                                    <input
                                                        type="text"
                                                        v-model="userSearchTerm"
                                                        placeholder="Search users..."
                                                        class="w-full p-2 text-sm rounded-md bg-gray-700 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500"
                                                    />
                                                    
                                                    <!-- Selected users chips -->
                                                    <div v-if="activeUserIds.length > 0" class="flex flex-wrap gap-1 p-2 bg-gray-700 rounded-md min-h-[2rem]">
                                                        <span
                                                            v-for="userId in activeUserIds"
                                                            :key="userId"
                                                            class="inline-flex items-center px-2 py-1 bg-teal-600 text-white text-xs rounded-md"
                                                        >
                                                            {{ allUsers.find(u => u.id === userId)?.name || 'Unknown User' }}
                                                            <button
                                                                @click="selectUserFilter(userId)"
                                                                class="ml-1 text-teal-200 hover:text-white font-bold"
                                                                title="Remove user"
                                                            >
                                                                ×
                                                            </button>
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- User list -->
                                                    <div class="max-h-32 overflow-y-auto custom-scrollbar rounded-md">
                                                        <button
                                                            v-for="user in filteredUsers"
                                                            :key="user.id"
                                                            :class="[
                                                                'flex items-center space-x-2 px-3 py-1.5 rounded-md mt-2 text-white cursor-pointer w-full text-left',
                                                                'transition-all duration-100 ease-in-out hover:brightness-110',
                                                                userFilterColor,
                                                                activeUserIds.includes(user.id) ? 'bg-teal-700 ring-1 ring-teal-400' : ''
                                                            ]"
                                                            @click="selectUserFilter(user.id)"
                                                        >
                                                            <span class="text-base">
                                                                <UserIcon class="w-4 h-4" />
                                                            </span>
                                                            <span class="font-normal text-xs flex-grow">{{ user.name }}</span>
                                                            <span v-if="activeUserIds.includes(user.id)" class="text-teal-200 font-bold">✓</span>
                                                        </button>
                                                        
                                                        <p v-if="filteredUsers.length === 0 && userSearchTerm.trim()" class="text-gray-400 text-center text-xs py-2">
                                                            No users found matching "{{ userSearchTerm }}"
                                                        </p>
                                                        <p v-else-if="allUsers.length === 0" class="text-gray-400 text-center text-xs py-2">
                                                            Loading users...
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Exponential Scale Filter (only show for line chart) -->
                                            <div v-if="currentChart.component === 'DashboardLineChartComponent'">
                                                <h4 class="text-white font-semibold text-sm mb-2">Performance Scale</h4>
                                                <button
                                                    @click="toggleExponentialScale"
                                                    :class="[
                                                        'flex items-center space-x-2 p-3 rounded-lg text-white cursor-pointer text-sm w-full',
                                                        'transition-all duration-300 ease-in-out',
                                                        performanceFilterColor + ' hover:brightness-90',
                                                        isExponentialScale ? 'bg-purple-700 ring-2 ring-purple-400' : ''
                                                    ]"
                                                >
                                                    <ScalingIcon class="w-5 h-5 shrink-0" />
                                                    <span class="font-medium">{{ isExponentialScale ? 'Exponential ON' : 'Exponential OFF' }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile: Chart section (when filters are collapsed) -->
                <div class="block lg:hidden space-y-8 p-2 rounded-lg relative">
                    <section>
                        <div class="w-full">
                            <transition name="chart-fade" mode="out-in">
                                <div :key="currentChartIndex" class="w-full">
                                    <DashboardLineChartComponent
                                        v-if="currentChart.component === 'DashboardLineChartComponent'"
                                        :game-type-id="activeGameId"
                                        :start-date="dateRange[0]"
                                        :end-date="dateRange[1]"
                                        :is-exponential-scale="isExponentialScale"
                                        :user-ids="activeUserIds"
                                        :difficulty-id="difficultyId"
                                        :category-id="categoryId"
                                        :show-ai-scores="showAiScores" 
                                        @pointClicked="handleChartPointClick"
                                        class="w-full" />
                                    <DashboardHeatmapComponent
                                        v-else-if="currentChart.component === 'DashboardHeatmapComponent'"
                                        :game-type-id="activeGameId"
                                        :start-date="dateRange[0]"
                                        :end-date="dateRange[1]"
                                        :user-ids="activeUserIds"
                                        :and-users="andUsers"
                                        :difficulty-id="difficultyId"
                                        :category-id="categoryId"
                                        :show-ai-scores="showAiScores"
                                        class="w-full"
                                    />
                                </div>
                            </transition>
                        </div>
                    </section>
                </div>
          </div>
      </div>
  </section>

  <!-- Date Modal - Make it more mobile friendly -->
  <div v-if="showDateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-gray-800 rounded-lg shadow-xl p-4 relative w-full max-w-md min-h-[500px] flex flex-col">
          <h3 class="text-xl font-semibold text-white mb-4">Select Date Range</h3>
          <button
              @click="showDateModal = false"
              class="absolute top-4 right-4 text-gray-400 hover:text-gray-200"
          >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
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
</template>

<style scoped>
/* Dropdown animation */
.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Close dropdowns when clicking outside */
.relative {
    z-index: 10;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.fade-slide-y-enter-active,
.fade-slide-y-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-slide-y-enter-from,
.fade-slide-y-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.chart-fade-enter-active,
.chart-fade-leave-active {
  transition: opacity 0.3s ease;
}
.chart-fade-enter-from,
.chart-fade-leave-to {
  opacity: 0;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: #333; /* Darker track */
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #555; /* Darker thumb */
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #777; /* Even darker on hover */
}
</style>