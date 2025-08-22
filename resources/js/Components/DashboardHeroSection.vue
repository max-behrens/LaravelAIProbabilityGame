<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { usePage } from '@inertiajs/inertia-vue3';
import { Trophy, ChartColumnStacked, LineChartIcon } from 'lucide-vue-next';
import axios from 'axios';

// Accept props from parent component
const props = defineProps({
  gameTypes: {
    type: Array,
    default: () => []
  },
  gameWins: {
    type: Object,
    default: () => ({ player_wins: 0, ai_wins: 0 })
  },
  difficulties: {
    type: Array,
    default: () => []
  },
  categories: {
    type: Array,
    default: () => []
  }
});

// Debug: Also check if data is available directly from page
const page = usePage();

const gameTypes = page.props.gameTypes || null;

// Game wins data - store for each game type
const gameWinsData = ref({});
const currentSlide = ref(1);
const gameWinsLoading = ref({});

// Filter states
const selectedDifficulty = ref(null); // null means "All"
const selectedCategory = ref(null); // null means "All"

// Image loading states
const loadedImages = ref([]);
const preloadedImages = ref([]);
const imagesReady = ref(false);

// Initialize game wins data for each game type
const initializeGameWinsData = async () => {
  const gameTypesToUse = props.gameTypes?.length > 0 ? props.gameTypes : page.props.game_types;
  
  if (gameTypesToUse) {
    for (const gameType of gameTypesToUse) {
      gameWinsLoading.value[gameType.id] = true;
      gameWinsData.value[gameType.id] = await fetchGameWinsForType(gameType.id);
      gameWinsLoading.value[gameType.id] = false;
    }
  }
};

// Get current game wins based on selected slide
const currentGameWins = computed(() => {
  const gameTypesToUse = props.gameTypes?.length > 0 ? props.gameTypes : page.props.game_types;
  if (gameTypesToUse && gameTypesToUse[currentSlide.value]) {
    const gameTypeId = gameTypesToUse[currentSlide.value].id;
    return gameWinsData.value[gameTypeId] || { player_wins: 0, ai_wins: 0 };
  }
  return { player_wins: 0, ai_wins: 0 };
});

// Check if current game wins data is loading
const currentGameWinsLoading = computed(() => {
  const gameTypesToUse = props.gameTypes?.length > 0 ? props.gameTypes : page.props.game_types;
  if (gameTypesToUse && gameTypesToUse[currentSlide.value]) {
    const gameTypeId = gameTypesToUse[currentSlide.value].id;
    return gameWinsLoading.value[gameTypeId] === true;
  }
  return false;
});

// Generate slides dynamically based on game types
const generateSlides = () => {
  const baseSlides = [
    {
      src: '/images/vecteezy_domesticated-black-donkeys-in-the-paddock-on-the-farm-pets_49542847.jpg',
      alt: 'Object Detection Game',
      title: 'Object Detection Game',
      description: 'Play against the AI to identify objects from a variety of images...',
    },
    {
      src: '/images/person-with-futuristic-metaverse-avatar-mask.jpg',
      alt: 'Game of Lies', 
      title: 'Game of Lies',
      description: 'Determine whether your AI opponent will choose the correct or incorrect answer to each question you do...',
    }
  ];

  // Try both sources to see which one has the data
  const gameTypesToUse = props.gameTypes?.length > 0 ? props.gameTypes : page.props.game_types;
  
  console.log('Using gameTypes:', gameTypesToUse);

  return baseSlides.map((slide, index) => ({
    ...slide,
    button1: { text: 'View AI Models', href: '/models' },
    button2: { 
      text: 'Find a Game', 
      href: gameTypesToUse?.[index] 
        ? route('ai-game', { game_type: gameTypesToUse[index].id })
        : '/dashboard'  // fallback if game type doesn't exist
    }
  }));
};

const slides = ref([]);

// Preload all images
const preloadImages = () => {
  return Promise.all(
    slides.value.map((slide, index) => {
      return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
          loadedImages.value[index] = true;
          preloadedImages.value[index] = img;
          resolve(img);
        };
        img.onerror = reject;
        // Set loading priority for first image
        if (index === 0) {
          img.fetchPriority = 'high';
        }
        img.src = slide.src;
      });
    })
  ).then(() => {
    imagesReady.value = true;
  });
};

const nextSlide = () => {
  currentSlide.value = (currentSlide.value + 1) % slides.value.length;
};

const goToSlide = (index) => {
  currentSlide.value = index;
};

const scrollToSection = (sectionId) => {
  const section = document.getElementById(sectionId);
  if (section) {
    const yOffset = -80;
    const y = section.getBoundingClientRect().top + window.pageYOffset + yOffset;
    window.scrollTo({ top: y, behavior: 'smooth' });
  }
};

const scrollToFeatured = () => {
  scrollToSection('stats');
};

const scrollToHeatmap = () => {
  // Dispatch custom event to switch to heatmap
  const event = new CustomEvent('switchChart', { 
    detail: { chartIndex: 0, chartType: 'heatmap' },
    bubbles: true 
  });
  document.dispatchEvent(event);
  
  // Small delay to ensure chart switches before scrolling
  setTimeout(() => {
    scrollToSection('stats');
  }, 100);
};

const scrollToLineChart = () => {
  // Dispatch custom event to switch to line chart
  const event = new CustomEvent('switchChart', { 
    detail: { chartIndex: 1, chartType: 'linechart' },
    bubbles: true 
  });
  document.dispatchEvent(event);
  
  // Small delay to ensure chart switches before scrolling
  setTimeout(() => {
    scrollToSection('stats');
  }, 100);
};

const goToLeaderboard = () => {
  window.location.href = route('leaderboard');
};

const handleImageLoad = (index) => {
  loadedImages.value[index] = true;
};

// Fetch game wins for specific game type
const fetchGameWinsForType = async (gameTypeId, difficultyId = null, categoryId = null) => {
  try {
    const params = new URLSearchParams();
    params.append('game_type_id', gameTypeId);
    if (difficultyId) params.append('difficulty_id', difficultyId);
    if (categoryId) params.append('category_id', categoryId);
    
    const response = await axios.get(`/api/dashboard/game-wins?${params.toString()}`);
    return response.data;
  } catch (error) {
    console.error('Error fetching game wins:', error);
    return { player_wins: 0, ai_wins: 0 };
  }
};

// Update game wins data when filters change
const updateGameWinsWithFilters = async () => {
  const gameTypesToUse = props.gameTypes?.length > 0 ? props.gameTypes : page.props.game_types;
  
  if (gameTypesToUse) {
    for (const gameType of gameTypesToUse) {
      gameWinsLoading.value[gameType.id] = true;
      gameWinsData.value[gameType.id] = await fetchGameWinsForType(
        gameType.id,
        selectedDifficulty.value,
        selectedCategory.value
      );
      gameWinsLoading.value[gameType.id] = false;
    }
  }
};

// Cycle through difficulty options
const cycleDifficulty = () => {
  if (!props.difficulties || props.difficulties.length === 0) return;
  
  if (selectedDifficulty.value === null) {
    // Start with first difficulty
    selectedDifficulty.value = props.difficulties[0].id;
  } else {
    // Find current index and move to next, or back to "All"
    const currentIndex = props.difficulties.findIndex(d => d.id === selectedDifficulty.value);
    if (currentIndex >= props.difficulties.length - 1) {
      selectedDifficulty.value = null; // Back to "All"
    } else {
      selectedDifficulty.value = props.difficulties[currentIndex + 1].id;
    }
  }
};

// Cycle through category options
const cycleCategory = () => {
  if (!props.categories || props.categories.length === 0) return;
  
  if (selectedCategory.value === null) {
    // Start with first category
    selectedCategory.value = props.categories[0].id;
  } else {
    // Find current index and move to next, or back to "All"
    const currentIndex = props.categories.findIndex(c => c.id === selectedCategory.value);
    if (currentIndex >= props.categories.length - 1) {
      selectedCategory.value = null; // Back to "All"
    } else {
      selectedCategory.value = props.categories[currentIndex + 1].id;
    }
  }
};

// Get display text for current difficulty
const currentDifficultyText = computed(() => {
  if (selectedDifficulty.value === null) return 'All';
  const difficulty = props.difficulties?.find(d => d.id === selectedDifficulty.value);
  return difficulty ? difficulty.name : 'All';
});

// Get display text for current category
const currentCategoryText = computed(() => {
  if (selectedCategory.value === null) return 'All';
  const category = props.categories?.find(c => c.id === selectedCategory.value);
  return category ? category.name : 'All';
});

// Watch for filter changes and update data
watch([selectedDifficulty, selectedCategory], () => {
  updateGameWinsWithFilters();
});

let slideInterval;

onMounted(async () => {
  // Generate slides when component mounts
  slides.value = generateSlides();
  loadedImages.value = slides.value.map(() => false);
  
  console.log('Generated slides:', slides.value);
  console.log('Props gameTypes on mount:', props.gameTypes);
  
  // Start preloading images immediately
  preloadImages().catch(console.error);
  
  // Initialize game wins data for all game types
  await initializeGameWinsData();
  
  // Only start slideshow after images are ready
  setTimeout(() => {
    slideInterval = setInterval(nextSlide, 15000);
  }, 1000);
});

onUnmounted(() => {
  clearInterval(slideInterval);
});
</script>

<template>
  <section class="min-h-screen flex items-center justify-center relative">

    <!-- Top Left Navigation Buttons -->
    <div class="main-width absolute top-8 left-8 z-20 flex flex-col space-y-2">
    <!-- Heatmap -->
    <button
      @click="scrollToHeatmap"
      class="theme-button-heatmap px-4 py-2 !text-white rounded-lg backdrop-blur-sm hover:scale-105 flex items-center space-x-2"
    >
      <ChartColumnStacked class="w-5 h-5 mr-2" />
      Scores Per Game
    </button>

    <!-- Line Chart -->
    <button
      @click="scrollToLineChart"
      class="theme-button-linechart px-4 py-2 !text-white rounded-lg backdrop-blur-sm hover:scale-105 flex items-center space-x-2"
    >
      <LineChartIcon class="w-5 h-5 mr-2" />
      Scores Over Time
    </button>
    </div>

    <!-- Top Right Leaderboard Button -->
    <div class="main-width absolute top-8 right-8 z-20">
      <!-- Leaderboard -->
      <button
        @click="goToLeaderboard"
        class="theme-button px-4 py-2 !text-white rounded-lg backdrop-blur-sm hover:scale-105 flex items-center space-x-2"
        aria-label="Go to leaderboard"
      >
        <Trophy class="w-5 h-5" />
        <span class="hidden sm:inline">Leaderboard</span>
      </button>
    </div>

    <!-- Game Wins Chart - Top Center -->
    <div class="flex flex-col">
      <!-- Filter Buttons -->
      <div class="z-20 mb-2 flex space-x-2 justify-center">
        <button
          @click="cycleDifficulty"
          class="px-3 py-1 bg-white/20 hover:bg-white/30 text-white text-xs rounded backdrop-blur-sm transition-all"
          v-if="difficulties && difficulties.length > 0"
        >
          Difficulty: {{ currentDifficultyText }}
        </button>
        <button
          @click="cycleCategory"
          class="px-3 py-1 bg-white/20 hover:bg-white/30 text-white text-xs rounded backdrop-blur-sm transition-all"
          v-if="categories && categories.length > 0"
        >
          Category: {{ currentCategoryText }}
        </button>
      </div>

      <div class="z-20 rounded-lg p-4 min-w-64">
        <div class="flex items-center justify-center mb-2">
          <h3 class="text-white text-sm font-semibold">Game Wins</h3>
          <!-- Loading spinner -->
          <div 
            v-if="currentGameWinsLoading"
            class="ml-2 animate-spin rounded-full h-4 w-4 border-b-2 border-white"
          ></div>
        </div>
        
        <!-- Labels -->
        <div class="flex justify-between text-xs mb-1">
          <div class="text-blue-200">Players: {{ currentGameWins.player_wins }}</div>
          <div class="text-red-200">AI: {{ currentGameWins.ai_wins }}</div>
        </div>
        
        <!-- Single combined bar -->
        <div class="bg-gray-600/30 h-6 rounded-full overflow-hidden flex relative">
          <!-- Player section -->
          <div 
            class="bg-blue-500/60 transition-all duration-500 flex items-center justify-start pl-2" 
            :style="{ width: (currentGameWins.player_wins + currentGameWins.ai_wins) > 0 ? `${(currentGameWins.player_wins / (currentGameWins.player_wins + currentGameWins.ai_wins)) * 100}%` : '50%' }"
          >
            <span 
              v-if="currentGameWins.player_wins > 0" 
              class="text-xs text-white font-semibold"
            >
              {{ currentGameWins.player_wins }}
            </span>
          </div>
          <!-- AI section -->
          <div 
            class="bg-red-500/60 transition-all duration-500 flex items-center justify-end pr-2" 
            :style="{ width: (currentGameWins.player_wins + currentGameWins.ai_wins) > 0 ? `${(currentGameWins.ai_wins / (currentGameWins.player_wins + currentGameWins.ai_wins)) * 100}%` : '50%' }"
          >
            <span 
              v-if="currentGameWins.ai_wins > 0" 
              class="text-xs text-white font-semibold"
            >
              {{ currentGameWins.ai_wins }}
            </span>
          </div>
        </div>
        
        <!-- Optional: Total count below -->
        <div class="text-center text-xs text-gray-300 mt-3">
          Total Games: {{ currentGameWins.player_wins + currentGameWins.ai_wins }}
        </div>
      </div>

      <!-- Background slideshow -->
      <div class="absolute inset-0 z-0">
        <div class="absolute inset-0">
          <div
            v-for="(slide, index) in slides"
            :key="`slide-${index}`"
            class="absolute inset-0"
            :class="[
              'transition-opacity duration-1000 ease-in-out',
              index === currentSlide && imagesReady ? 'opacity-100' : 'opacity-0'
            ]"
          >
            <img
              :src="slide.src"
              :alt="slide.alt"
              @load="handleImageLoad(index)"
              :class="[
                'absolute inset-0 w-full h-full object-cover transition-opacity duration-1000 ease-in-out',
                loadedImages[index] ? 'opacity-100' : 'opacity-0'
              ]"
            />
          </div>
        </div>
        <!-- Overlay for better text readability -->
        <div class="absolute inset-0 bg-black opacity-40"></div>
      </div>

      <!-- Content -->
      <div class="text-center z-10 px-6">
        <transition name="fade" appear mode="out-in">
          <div :key="currentSlide" class="max-w-4xl mx-auto fade-sides-bg rounded-lg p-4">
            <h1 class="text-5xl md:text-7xl font-bold mb-6 !text-white" style="font-size: 20pt !important;">
              {{ slides[currentSlide]?.title }}
            </h1>
            <div class="text-xl md:text-2xl text-blue-200 mb-8">
              <span class="typewriter" style="font-size: 15pt !important;">{{ slides[currentSlide]?.description }}</span>
            </div>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
              <a
                :href="slides[currentSlide]?.button1.href"
                class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-large rounded-lg transition-colors"
              >
                {{ slides[currentSlide]?.button1.text }}
              </a>
              <a
                :href="slides[currentSlide]?.button2.href"
                class="inline-flex items-center px-6 py-3 bg-transparent !text-black border border-white bg-white/70 hover:bg-white hover:text-slate-900 font-large rounded-lg transition-colors"
              >
                {{ slides[currentSlide]?.button2.text }}
              </a>
            </div>
          </div>
        </transition>
      </div>

    </div>

    <!-- Navigation dots -->
    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 flex space-x-3 z-20">
      <button
        v-for="(slide, index) in slides"
        :key="index"
        @click="goToSlide(index)"
        class="w-3 h-3 rounded-full transition-all hover:scale-110"
        :class="index === currentSlide ? 'bg-white shadow-lg' : 'bg-white/50 hover:bg-white/70'"
        aria-label="Go to slide"
      ></button>
    </div>

    <!-- Next button -->
    <button
      @click="nextSlide"
      class="absolute top-1/2 sm:right-8 right-4 z-20 -translate-y-1/2 
            bg-white/20 hover:bg-white/30 !text-white p-3 rounded-full 
            transition-all duration-300 backdrop-blur-sm hover:scale-110"
      aria-label="Next slide"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </button>

    <!-- Down button -->
    <button
      @click="scrollToFeatured"
      class="absolute z-20 bg-white/20 hover:bg-white/30 !text-white p-4 rounded-full transition-all duration-300 backdrop-blur-sm hover:scale-110 
            bottom-20 left-8 -translate-x-1/2 sm:bottom-auto sm:left-4 sm:translate-x-0"
      aria-label="Scroll to featured section"
    >
      <div class="animate-bounce">
        <svg class="w-6 h-6 group-hover:translate-y-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
      </div>
    </button>
  </section>
</template>

<style scoped>
.typewriter {
  border-right: 2px solid #fff;
  animation: blink 1s infinite;
}

@keyframes blink {
  0%,
  50% {
    border-color: #fff;
  }
  51%,
  100% {
    border-color: transparent;
  }
}

.fade-enter-active,
.fade-leave-active,
.crossfade-enter-active,
.crossfade-leave-active {
  transition: opacity 0.8s ease;
}

.fade-enter-from,
.crossfade-enter-from,
.fade-leave-to,
.crossfade-leave-to {
  opacity: 0;
}

.fade-enter-to,
.crossfade-enter-to,
.fade-leave-from,
.crossfade-leave-from {
  opacity: 1;
}

@keyframes bounce {
  0%,
  20%,
  53%,
  80%,
  100% {
    transform: translate3d(0, 0, 0);
  }
  40%,
  43% {
    transform: translate3d(0, -8px, 0);
  }
  70% {
    transform: translate3d(0, -4px, 0);
  }
  90% {
    transform: translate3d(0, -2px, 0);
  }

  /* Add extended idle time between bounces */
  101%,
  100% {
    transform: translate3d(0, 0, 0);
  }
}

.animate-bounce {
  animation: bounce 10s ease-in-out infinite;
}

.fade-sides-bg {
  background: linear-gradient(
    to right,
    transparent 0%,
    rgba(0, 0, 0, 0.5) 10%,
    rgba(0, 0, 0, 0.5) 90%,
    transparent 100%
  );
}</style>