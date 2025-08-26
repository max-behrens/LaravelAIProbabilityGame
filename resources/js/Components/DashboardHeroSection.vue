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
      description: 'Play with the AI to identify objects from a variety of images...',
    },
    {
      src: '/images/person-with-futuristic-metaverse-avatar-mask.jpg',
      alt: 'Fake or Steal', 
      title: 'Fake or Steal',
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
    <div class="flex flex-col main-width">
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
        <div class="flex items-center justify-center">
          <h3 class="!text-white text-sm font-semibold">Game Wins</h3>
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
              class="text-xs !text-white font-semibold"
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
              class="text-xs !text-white font-semibold"
            >
              {{ currentGameWins.ai_wins }}
            </span>
          </div>
        </div>
        
        <!-- Total games count -->
        <div class="text-center text-xs text-gray-100 mt-3">
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


      <div class="flex flex-col">
        <!-- Content -->
        <div class="text-center z-10 px-6">
          <transition name="fade" appear mode="out-in">
            <div :key="currentSlide" class="max-w-4xl mx-auto fade-sides-bg rounded-lg p-4 relative">
              
              <!-- Coming Soon Badge - Only for Object Detection Game (slide 0) -->
              <div 
                v-if="currentSlide === 0 && slides[currentSlide]?.title === 'Object Detection Game'"
                class="coming-soon-badge"
              >
                <span class="text-sm font-bold text-white">COMING SOON</span>
              </div>
              
              <h1 class="text-5xl md:text-7xl font-bold mb-6 !text-white" style="font-size: 20pt !important;">
                {{ slides[currentSlide]?.title }}
              </h1>

              <!-- Icons Section -->
              <div class="flex justify-center items-center space-x-8 mb-8">
                <!-- Multiplayer Icon -->
                <div class="flex flex-col items-center game-feature-icon">
                  <div class="bg-white/20 rounded-full p-4 mb-2 hover:bg-white/30 transition-all duration-300 hover:-translate-y-1">
                    <svg class="w-8 h-8 !text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                  </div>
                  <span class="text-xs text-white/80 text-center max-w-20">Single & Multiplayer games with AI models</span>
                </div>

                <!-- Form with Steal/Image Icon -->
                <div class="flex flex-col items-center game-feature-icon">
                  <div class="bg-white/20 rounded-full p-4 mb-2 hover:bg-white/30 transition-all duration-300 hover:-translate-y-1 relative">
                    <!-- Base Form Icon -->
                    <svg class="w-8 h-8 !text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    
                    <!-- Overlapping icon - changes based on current slide -->
                    <div class="absolute -top-1 -right-1 bg-blue-500 rounded-full p-1">
                      <!-- Steal icon for Object Detection Game (slide 0) -->
                      <svg 
                        v-if="currentSlide === 0" 
                        class="w-6 h-6 !text-white" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                      >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      <!-- Image icon for Fake or Steal (slide 1) -->
                      <svg 
                        v-else 
                        class="w-6 h-6 !text-white" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                      >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                      </svg>
                    </div>
                  </div>
                  <span class="text-xs text-white/80 text-center max-w-24">
                    {{ currentSlide === 0 ? "Decipher images and select photos for AI" : "Choose to steal AI or player's answers" }}
                  </span>
                </div>

                <!-- AI Model Icon -->
                <div class="flex flex-col items-center game-feature-icon">
                  <div class="bg-white/20 rounded-full p-4 mb-2 hover:bg-white/30 transition-all duration-300 hover:-translate-y-1">
                    <svg class="w-8 h-8 !text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <span class="text-xs text-white/80 text-center max-w-20">AIs that know your history and patterns</span>
                </div>
              </div>

              <div class="flex flex-col sm:flex-row gap-4 justify-center mb-6">
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
              <div class="text-xl md:text-2xl text-blue-200 mb-4">
                <span style="font-size: 15pt !important;">{{ slides[currentSlide]?.description }}</span>
              </div>
            </div>
          </transition>
        </div>
      </div>

      <!-- Navigation dots -->
      <div class="flex justify-center mt-3 space-x-3 z-20">
        <button
          v-for="(slide, index) in slides"
          :key="index"
          @click="goToSlide(index)"
          class="w-4 h-4 rounded-full transition-all hover:scale-110"
          :class="index === currentSlide ? 'bg-white/80 shadow-lg' : 'bg-white/50 hover:bg-white/30'"
          aria-label="Go to slide"
        ></button>
      </div>
    </div>

    <!-- Next button -->
    <button
      @click="nextSlide"
      class="absolute sm:top-1/2 top-1/2 sm:right-10 right-1 main-width z-20 -translate-y-1/2 
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
      class="absolute z-20 main-width bg-white/20 hover:bg-white/30 !text-white p-4 rounded-full transition-all duration-300 backdrop-blur-sm hover:scale-110 
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

/* Animate Bounce CSS */
.animate-bounce {
  animation: bounce 10s ease-in-out infinite;
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

/* Game Feature Icons Animation */
.game-feature-icon {
  animation: gentle-bounce 8s ease-in-out infinite;
}

.game-feature-icon:nth-child(1) {
  animation-delay: 0s;
}

.game-feature-icon:nth-child(2) {
  animation-delay: 0.3s;
}

.game-feature-icon:nth-child(3) {
  animation-delay: 0.5s;
}

@keyframes gentle-bounce {
  0%, 20%, 50%, 80%, 100% {
    transform: translateY(0);
  }
  40% {
    transform: translateY(-4px);
  }
  60% {
    transform: translateY(-2px);
  }
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
}

/* Coming Soon Badge Styles */
.coming-soon-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: linear-gradient(135deg, #ff6b6b, #ee5a24);
  transform: rotate(12deg);
  padding: 6px 16px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(238, 90, 36, 0.4);
  z-index: 10;
  animation: pulse-glow 2s ease-in-out infinite;
  border: 2px solid rgba(255, 255, 255, 0.3);
}

.coming-soon-badge::before {
  content: '';
  position: absolute;
  inset: -2px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), transparent);
  border-radius: 14px;
  z-index: -1;
}

@keyframes pulse-glow {
  0%, 100% {
    box-shadow: 0 4px 12px rgba(238, 90, 36, 0.4);
    transform: rotate(12deg) scale(1);
  }
  50% {
    box-shadow: 0 6px 20px rgba(238, 90, 36, 0.6);
    transform: rotate(12deg) scale(1.05);
  }
}
</style>