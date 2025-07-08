<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, router, usePage } from '@inertiajs/inertia-vue3';
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { ChartBarIcon, PuzzleIcon, Gamepad2Icon, ClockIcon, TrendingUpIcon, EyeIcon, CalendarDaysIcon, ScalingIcon, UserIcon } from 'lucide-vue-next';
import HeroSection from '@/Components/HeroSection.vue';
import DashboardGameDetails from '@/Components/DashboardGameDetails.vue';
import DashboardAIDetails from '@/Components/DashboardAIDetails.vue';
import DashboardHeatmapComponent from '@/Components/DashboardHeatmapComponent.vue';
import DashboardBarChartComponent from '@/Components/DashboardBarChartComponent.vue';
import DashboardLineChartComponent from '@/Components/DashboardLineChartComponent.vue';
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import axios from 'axios';

// Get initial URL parameters
const page = usePage();
const initialGameId = page.props.current_game_id || null;
const initialStartDate = page.props.current_start_date ? new Date(page.props.current_start_date) : null;
const initialEndDate = page.props.current_end_date ? new Date(page.props.current_end_date) : null;
const initialExponentialScale = page.props.current_exponential_scale === 'true';
const initialUserId = page.props.current_user_id || null;

const showNav = ref(false);
const currentSection = ref(0);
const showVerticalNav = ref(false);
const currentNavSection = ref(0);
const gameScores = ref([]);
const gameHeatmapRef = ref(null);
const showAdvancedFilters = ref(false);


// Filter States
const activeGameId = ref(initialGameId);
const showDateModal = ref(false);

// Keep dateRange as originally intended: empty if no URL params
const dateRange = ref([initialStartDate, initialEndDate]);

// **NEW:** Computed property for the Datepicker's v-model
// This ensures the datepicker opens to today if dateRange is currently null/empty
const datepickerModel = computed({
    get() {
        // If dateRange is [null, null], provide today's date for the datepicker modal to open on.
        // Otherwise, return the actual dateRange values.
        if (!dateRange.value[0] && !dateRange.value[1]) {
            return [new Date(), new Date()];
        }
        return dateRange.value;
    },
    set(newValue) {
        // When the datepicker updates its model, we update the actual dateRange ref.
        // This setter ensures the link back to dateRange is maintained.
        // The handleDateSelection will then perform the validation and actual assignment.
        handleDateSelection(newValue);
    }
});


const isExponentialScale = ref(initialExponentialScale);

// User Filter States
const activeUserId = ref(initialUserId);
const userSearchTerm = ref('');
const allUsers = ref([]);
const userFilterColor = 'bg-teal-600/50';

// Map selectedGame index to actual game IDs and names
const gameFilters = [
  { id: 1, name: 'Object Detection Game' },
  { id: 2, name: 'Game of Lies' }
];

const gameIcons = [PuzzleIcon, Gamepad2Icon, ChartBarIcon];
const performanceIcons = [ClockIcon, TrendingUpIcon, EyeIcon];
const UserIcons = [UserIcon, UserIcon, UserIcon];

// Adjusted colors for better active state visibility
const optionColors = ['bg-yellow-600/50', 'bg-blue-600/50'];
const dateFilterColor = 'bg-orange-600/50';
const performanceFilterColor = 'bg-purple-600/50';

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

// Computed property for the dynamic user filter title
const userFilterTitle = computed(() => {
  if (activeUserId.value === null) {
    return 'User Filter - All Users';
  }
  const selectedUser = allUsers.value.find(user => user.id === activeUserId.value);
  return selectedUser ? `User Filter - ${selectedUser.name}` : 'User Filter';
});

// --- Filter Action Functions ---

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
  if (activeUserId.value === userId) {
    activeUserId.value = null; // Deselect
  } else {
    activeUserId.value = userId; // Select
  }
};

// Function to toggle exponential scale
const toggleExponentialScale = () => {
  isExponentialScale.value = !isExponentialScale.value;
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

watch([activeGameId, dateRange, isExponentialScale, activeUserId], ([newGameId, newDateRange, newIsExponentialScale, newUserId]) => {
  const params = {};
  if (newGameId !== null) {
    params.game_id = newGameId;
  }
  if (newDateRange && newDateRange[0] && newDateRange[1]) {
    const startDate = newDateRange[0] instanceof Date ? newDateRange[0] : new Date(newDateRange[0]);
    const endDate = newDateRange[1] instanceof Date ? newDateRange[1] : new Date(newDateRange[1]);
    
    if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
      params.start_date = startDate.toISOString().split('T')[0];
      params.end_date = endDate.toISOString().split('T')[0];
    }
  } else {
    delete params.start_date;
    delete params.end_date;
  }

  if (newIsExponentialScale) {
    params.exponential_scale = 'true';
  } else {
    if (page.props.current_exponential_scale === 'true') {
      params.exponential_scale = 'false';
    } else {
      delete params.exponential_scale;
    }
  }
  if (newUserId !== null) {
    params.user_id = newUserId;
  } else {
    delete params.user_id;
  }
  router.get(route('dashboard'), params, { preserveState: true, replace: true });
}, { immediate: false });


const slides = [
  {
    src: '/images/vecteezy_domesticated-black-donkeys-in-the-paddock-on-the-farm-pets_49542847.jpg',
    alt: 'Object Detection Game',
    title: 'Object Detection Game',
    description: 'Play against the AI to identify objects from a variety of images...',
    button1: { text: 'View AI Models', href: '/models' },
    button2: { text: 'Find a Game', href: '/demo' }
  },
  {
    src: '/images/person-with-futuristic-metaverse-avatar-mask.jpg',
    alt: 'Game of Lies',
    title: 'Game of Lies',
    description: 'Determine whether your AI opponent will choose the correct or incorrect answer to each question you do...',
    button1: { text: 'View AI Models', href: '/avatars' },
    button2: { text: 'Find a Game', href: '/metaverse-demo' }
  }
];

watch(currentSection, (newVal) => {
  showNav.value = newVal !== 0;
});

const mobileMenuOpen = ref(false);
const currentSlide = ref(0);

const navSections = [
  { id: 'main', name: 'Home' },
  { id: 'stats', name: 'Stats' },
  { id: 'game-details', name: 'Games' },
  { id: 'ai-details', name: 'AI' },
  { id: 'nature-collections', name: 'Collections' }
];

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
};

const nextSlide = () => {
    currentSlide.value = (currentSlide.value + 1) % slides.length;
};

const goToSlide = (index) => {
    currentSlide.value = index;
};

const navigateSection = (direction) => {
  const newIndex = direction === 'up'
    ? Math.max(0, currentNavSection.value - 1)
    : Math.min(navSections.length - 1, currentNavSection.value + 1);

  currentNavSection.value = newIndex;
  scrollToSection(newIndex);
};

const scrollToSection = (sectionIndex) => {
  const section = navSections[sectionIndex];
  if (section.id === 'main') {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } else {
    const element = document.getElementById(section.id);
    if (element) {
      const yOffset = -80;
      const y = element.getBoundingClientRect().top + window.pageYOffset + yOffset;
      window.scrollTo({ top: y, behavior: 'smooth' });
    }
  }
};

const updateNavigation = () => {
  const scrollY = window.scrollY;
  const windowHeight = window.innerHeight;

  showVerticalNav.value = scrollY > windowHeight * 0.3;

  const sectionElements = navSections.map((section, index) => ({
    element: document.getElementById(section.id),
    index: index
  })).filter(item => item.element);

  const scrollPosition = scrollY + windowHeight * 0.4;

  for (let i = sectionElements.length - 1; i >= 0; i--) {
    const { element, index } = sectionElements[i];
    if (element.offsetTop <= scrollPosition) {
      currentNavSection.value = index;
      break;
    }
  }
};

let slideInterval;
onMounted(async () => {
  slideInterval = setInterval(() => {
    nextSlide();
  }, 15000);

  window.addEventListener('scroll', updateNavigation);
  
  // Fetch users on mount
  await fetchUsers();

  nextTick(() => {
    updateNavigation();
  });
});

onUnmounted(() => {
  if (slideInterval) {
    clearInterval(slideInterval);
  }
  window.removeEventListener('scroll', updateNavigation);
});
</script>

<template>
    <Head title="Dashboard" />

    <BreezeAuthenticatedLayout>

        <section id="main">
            <HeroSection />
        </section>

          <transition name="fade">
            <div
            v-if="showVerticalNav"
            class="fixed left-4 top-1/2 transform -translate-y-1/2 z-50 bg-gray-800 backdrop-blur-sm rounded-lg p-2 shadow-lg"
            >
            <div class="flex flex-col space-y-2">
                <button 
                @click="navigateSection('up')"
                :disabled="currentNavSection === 0"
                class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed group"
                >
                <svg class="w-5 h-5 text-white group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                </svg>
                </button>
                
                <div class="text-center py-2">
                <div class="text-white text-xs font-medium whitespace-nowrap">
                    {{ navSections[currentNavSection]?.name || 'Main' }}
                </div>
                </div>
                
                <button 
                @click="navigateSection('down')"
                :disabled="currentNavSection === navSections.length - 1"
                class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed group"
                >
                <svg class="w-5 h-5 text-white group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                </button>
            </div>
            </div>
        </transition>

        <div class="py-2 max-w-7xl mx-auto px-6 lg:px-8">
        
            <section id="stats" class="py-10 bg-gray-800 rounded-lg">

                <div class="container mx-auto px-6">

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                        <div class="lg:col-span-3">

                              <div class="mb-4">
                                <h3 class="text-xl font-bold text-white mb-2">Performance Over Time</h3>
                                <p class="text-white text-xl">Track your game performance and progress</p>
                              </div>
                            
                            <div class="space-y-6">

                            <div class="grid grid-cols-2 md:grid-cols-2 gap-8 ml-6 mr-6">

                                  <div>
                                    <div class="bg-gray-800 p-6 rounded-lg mb-4">
                                          <h4 class="text-white font-semibold text-lg mb-4 text-center">{{ gameFilterTitle }}</h4>
                                          <div class="flex flex-col space-y-4">
                                            <button
                                                v-for="(filter, index) in gameFilters"
                                                :key="filter.id"
                                                :class="[
                                                    'flex items-center space-x-3 p-4 rounded-lg shadow-md text-white cursor-pointer',
                                                    'transition-all duration-300 ease-in-out',
                                                    optionColors[index] + ' hover:brightness-90',
                                                    activeGameId === filter.id ? 'bg-blue-700 ring-2 ring-blue-400' : ''
                                                ]"
                                                @click="selectGameFilter(filter.id)"
                                            >
                                                <span class="text-2xl transition-opacity duration-300 ease-in-out">
                                                    <component :is="gameIcons[index]" />
                                                </span>
                                                <span class="font-medium transition-opacity duration-300 ease-in-out">{{ filter.name }}</span>
                                            </button>
                                          </div>
                                      </div>
                                      <div class="bg-gray-800 px-6 rounded-lg">
                                          <button
                                              @click="showAdvancedFilters = !showAdvancedFilters"
                                              :class="[
                                                  'w-full flex items-center justify-center space-x-3 p-4 rounded-lg shadow-md text-white cursor-pointer',
                                                  'transition-all duration-300 ease-in-out',
                                                  'bg-gray-600 hover:bg-gray-700', // A neutral color for the toggle button
                                                  showAdvancedFilters ? 'bg-gray-700' : '' // Slightly darker when open
                                              ]"
                                          >
                                              <span class="text-2xl">
                                                  <template v-if="showAdvancedFilters">
                                                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-up"><path d="m18 15-6-6-6 6"/></svg>
                                                  </template>
                                                  <template v-else>
                                                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down"><path d="m6 9 6 6 6-6"/></svg>
                                                  </template>
                                              </span>
                                              <span class="font-medium">
                                                  {{ showAdvancedFilters ? 'Hide Advanced Filters' : 'Show Advanced Filters' }}
                                              </span>
                                          </button>

                                          <transition name="fade-slide-y">
                                              <div v-if="showAdvancedFilters" class="mt-4"> <div class="bg-gray-800 p-6 rounded-lg"> <h4 class="text-white font-semibold text-lg mb-4 text-center">{{ userFilterTitle }}</h4>
                                                      <div class="flex flex-col space-y-2">
                                                          <input
                                                              type="text"
                                                              v-model="userSearchTerm"
                                                              placeholder="Search users..."
                                                              class="w-full p-2 rounded-md bg-gray-700 text-white placeholder-gray-400 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-teal-500"
                                                          />
                                                          <div class="max-h-48 overflow-y-auto custom-scrollbar border border-gray-700 rounded-md">
                                                              <button
                                                                  v-for="user in filteredUsers"
                                                                  :key="user.id"
                                                                  :class="[
                                                                      'flex items-center space-x-2 px-3 py-1.5 rounded-md text-white cursor-pointer w-full text-left',
                                                                      'transition-all duration-100 ease-in-out',
                                                                      userFilterColor,
                                                                      activeUserId === user.id ? 'bg-teal-700 ring-1 ring-teal-400' : ''
                                                                  ]"
                                                                  @click="selectUserFilter(user.id)"
                                                              >
                                                                  <span class="text-base">
                                                                      <UserIcon />
                                                                  </span>
                                                                  <span class="font-normal text-sm">{{ user.name }}</span>
                                                              </button>
                                                              <p v-if="filteredUsers.length === 0 && userSearchTerm.trim()" class="text-gray-400 text-center text-sm py-2">
                                                                  No users found matching "{{ userSearchTerm }}"
                                                              </p>
                                                              <p v-else-if="allUsers.length === 0" class="text-gray-400 text-center text-sm py-2">
                                                                  Loading users...
                                                              </p>
                                                          </div>
                                                      </div>
                                                  </div>
                                              </div>
                                          </transition>
                                        </div>
                                    </div>

                                    <div class="row flex gap-4 items-start">

                                      <div class="bg-gray-800 p-6 rounded-lg flex flex-col justify-between">
                                          <div>
                                              <h4 class="text-white font-semibold text-lg mb-4 text-center">Date Picker</h4>
                                              <button
                                                  @click="showDateModal = true"
                                                  :class="[
                                                      'flex items-center space-x-3 p-4 rounded-lg shadow-md text-white cursor-pointer h-full',
                                                      'transition-all duration-300 ease-in-out',
                                                      dateFilterColor + ' hover:brightness-90',
                                                      (dateRange[0] && dateRange[1]) ? 'bg-orange-700 ring-2 ring-orange-400' : ''
                                                  ]"
                                              >
                                                  <span class="text-2xl transition-opacity duration-300 ease-in-out">
                                                      <CalendarDaysIcon />
                                                  </span>
                                                  <span class="font-medium transition-opacity duration-300 ease-in-out">{{ dateFilterTitle }}</span>
                                              </button>
                                          </div>
                                      </div>

                                      <div class="bg-gray-800 p-6 rounded-lg flex flex-col justify-between">
                                          <div>
                                              <h4 class="text-white font-semibold text-lg mb-4 text-center">Performance</h4>
                                              <button
                                                  @click="toggleExponentialScale"
                                                  :class="[
                                                      'flex items-center space-x-3 p-4 rounded-lg shadow-md text-white cursor-pointer h-full',
                                                      'transition-all duration-300 ease-in-out',
                                                      performanceFilterColor + ' hover:brightness-90',
                                                      isExponentialScale ? 'bg-purple-700 ring-2 ring-purple-400' : ''
                                                  ]"
                                              >
                                                  <span class="text-2xl transition-opacity duration-300 ease-in-out">
                                                      <ScalingIcon />
                                                  </span>
                                                  <span class="font-medium transition-opacity duration-300 ease-in-out">
                                                      {{ isExponentialScale ? 'Exponential Scale ON' : 'Exponential Scale OFF' }}
                                                  </span>
                                              </button>
                                          </div>
                                      </div>

                                    </div>

                                </div>

                                <div class="space-y-8 theme-bg-primary p-6 rounded-lg shadow-lg">
                                    <section>
                                        <div class="w-full">
                                            <DashboardLineChartComponent
                                                :game-id="activeGameId"
                                                :start-date="dateRange[0]"
                                                :end-date="dateRange[1]"
                                                :is-exponential-scale="isExponentialScale"
                                                :user-id="activeUserId"
                                            />
                                        </div>
                                    </section>
                                </div>

                                <div class="space-y-8">
                                    <section>
                                        <div class="w-full">
                                            <DashboardHeatmapComponent 
                                            />
                                        </div>
                                    </section>
                                </div>

                                <div class="space-y-8">
                                    <section>
                                        <DashboardBarChartComponent />
                                    </section>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="game-details" class="mb-16">
                <h2 class="text-3xl font-bold text-blue-600 mb-4 text-center">Game Details</h2>
                <DashboardGameDetails />
            </section>

            <section id="ai-details" class="mb-16">
                <h2 class="text-3xl font-bold text-blue-600 mb-4 text-center">AI Details</h2>
                <DashboardAIDetails />
            </section>

            <section id="nature-collections" class="mb-16">
                <h2 class="text-3xl font-bold text-blue-600 mb-4 text-center">Collections</h2>
                <div class="text-center mb-12">
                    <h3 class="text-2xl font-semibold text-blue-500 mb-4">Options</h3>
                    <p class="text-xl theme-text-secondary max-w-4xl mx-auto">
                        Taking inspiration from the organic shapes, movements and sequential patterns that surround us
                    </p>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="h-48 bg-gray-300 rounded-lg overflow-hidden">
                        <img alt="Nature Collection 1" class="w-full h-full object-cover">
                    </div>
                    <div class="h-48 bg-gray-300 rounded-lg overflow-hidden">
                        <img alt="Nature Collection 2" class="w-full h-full object-cover">
                    </div>
                    <div class="h-48 bg-gray-300 rounded-lg overflow-hidden">
                        <img alt="Nature Collection 3" class="w-full h-full object-cover">
                    </div>
                </div>
            </section>
        </div>

        <div v-if="showDateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-gray-800 rounded-lg shadow-xl p-6 relative max-w-md w-full">
                <h3 class="text-xl font-semibold text-white mb-4">Select Date Range</h3>
                <button
                    @click="showDateModal = false"
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-200"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <Datepicker
                    v-model="datepickerModel" range
                    :enable-time-picker="false"
                    :dark="true"
                    :teleport="true"
                    placeholder="Select Date Range"
                    :min-date="new Date('2024-01-01')"
                    :max-date="new Date()"
                    class="my-4"
                    @update:model-value="handleDateSelection"
                ></Datepicker>

                <div class="flex justify-end space-x-2 mt-4">
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
    </BreezeAuthenticatedLayout>
</template>


<style scoped>
.tech-icon {
    transition: all 0.3s ease;
}

.tech-icon:hover {
    transform: scale(1.1);
}

.fade-enter-active,
.fade-leave-active {
  transition: all 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

.fade-enter-to,
.fade-leave-from {
  opacity: 1;
  transform: translateX(0);
}

.crossfade-enter-active,
.crossfade-leave-active {
  transition: opacity 0.3s ease;
}
.crossfade-enter-from {
  opacity: 0;
}
.crossfade-leave-to {
  opacity: 0;
}
.crossfade-enter-to,
.crossfade-leave-from {
  opacity: 1;
}

.fade-slide-y-enter-active,
.fade-slide-y-leave-active {
  transition: all 0.3s ease-out;
  overflow: hidden; /* Ensures content doesn't "jump" during height transition */
}

.fade-slide-y-enter-from,
.fade-slide-y-leave-to {
  opacity: 0;
  transform: translateY(-10px);
  max-height: 0; /* Animate height from 0 */
  padding-top: 0;
  padding-bottom: 0;
  margin-top: 0;
  margin-bottom: 0;
}

.fade-slide-y-enter-to,
.fade-slide-y-leave-from {
  opacity: 1;
  transform: translateY(0);
  max-height: 500px; /* A value larger than the max possible height of the content */
  /* Restore original padding/margin if they were removed in -from state */
  padding-top: theme('padding.6'); /* or whatever your original padding-top was, e.g., p-6 means 1.5rem */
  padding-bottom: theme('padding.6');
  margin-top: theme('margin.4'); /* mt-4 */
  margin-bottom: 0; /* if no mb */
}

.hero-gradient-mask {
  mask-image: linear-gradient(to right, transparent 0%, black 50%, black 100%);
  -webkit-mask-image: linear-gradient(to right, transparent 0%, black 50%, black 100%);
}

html {
    scroll-behavior: smooth;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  @apply bg-gray-700;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  @apply bg-gray-500 rounded-full;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  @apply bg-gray-400;
}

:deep(.dp__theme_dark) {
  --dp-background-color: #1F2937;
  --dp-text-color: #F3F4F6;
  --dp-hover-color: #374151;
  --dp-hover-text-color: #F3F4F6;
  --dp-hover-icon-color: #F3F4F6;
  --dp-primary-color: #3B82F6;
  --dp-primary-text-color: #FFFFFF;
  --dp-secondary-color: #4B5563;
  --dp-border-color: #4B5563;
  --dp-border-color-hover: #6B7280;
  --dp-menu-border-color: #4B5563;
  --dp-disabled-color: #030405;
  --dp-scroll-bar-background: #4B5563;
  --dp-scroll-bar-color: #9CA3AF;
  --dp-success-color: #10B981;
  --dp-success-color-disabled: #4B5563;
  --dp-icon-color: #9CA3AF;
  --dp-danger-color: #EF4444;
  --dp-highlight-color: rgba(0, 92, 178, 0.2);
}

:deep(.dp__input) {
  background-color: #1F2937 !important;
  border-color: #4B5563 !important;
  color: #F3F4F6 !important;
}

:deep(.dp__calendar_header),
:deep(.dp__month_year_row) {
    color: #F3F4F6 !important;
}

:deep(.dp__calendar_header_item) {
    color: #9CA3AF !important;
}

:deep(.dp__cell_inner) {
    color: #F3F4F6 !important;
}

:deep(.dp__range_between) {
  background-color: rgba(59, 130, 246, 0.2) !important;
}

/* Datepicker action buttons */
:deep(.dp__action_buttons) button {
  @apply px-3 py-1 rounded-md text-white transition-colors duration-200;
}
:deep(.dp__action_buttons) .dp__action_select {
  @apply bg-blue-600 hover:bg-blue-700;
}
:deep(.dp__action_buttons) .dp__action_cancel {
  @apply bg-gray-600 hover:bg-gray-700;
}
</style>