<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import ChartComponent from '@/Components/ChartComponent.vue';
import HeroSection from '@/Components/HeroSection.vue';
import DashboardGameDetails from '@/Components/DashboardGameDetails.vue';
import DashboardAIDetails from '@/Components/DashboardAIDetails.vue';
import GameHeatmapComponent from '@/Components/GameHeatmapComponent.vue';
import LineChartComponent from '@/Components/LineChartComponent.vue';


const showNav = ref(false);
const currentSection = ref(0);
const showVerticalNav = ref(false);
const currentNavSection = ref(0);
const gameScores = ref([]);
const gameHeatmapRef = ref(null);



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

// Watch currentSection and update showNav based on section index
watch(currentSection, (newVal) => {
  // Show nav only if NOT on the main section (index 0)
  showNav.value = newVal !== 0;
});

// Navigation state
const mobileMenuOpen = ref(false);

// Slideshow state
const currentSlide = ref(0);

// Section navigation state
const navSections = [
  { id: 'main', name: 'Home' },
  { id: 'featured', name: 'Stats' },
  { id: 'models', name: 'Models' },
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
  
  // Show nav when scrolled past 30% of viewport height
  showVerticalNav.value = scrollY > windowHeight * 0.3;
  
  // Update current section based on scroll position
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
// Auto-advance slideshow every 15 seconds
let slideInterval;
onMounted(() => {
  slideInterval = setInterval(() => {
    nextSlide();
  }, 15000);

  // Replace scroll listener
  window.addEventListener('scroll', updateNavigation);
  
  // Initialize
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

          <!-- Vertical Navigation -->
        <transition name="fade">
            <div
            v-if="showVerticalNav"
            class="fixed left-4 top-1/2 transform -translate-y-1/2 z-50 bg-gray-800 backdrop-blur-sm rounded-lg p-2 shadow-lg"
            >
            <div class="flex flex-col space-y-2">
                <!-- Up Arrow -->
                <button 
                @click="navigateSection('up')"
                :disabled="currentNavSection === 0"
                class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed group"
                >
                <svg class="w-5 h-5 text-white group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                </svg>
                </button>
                
                <!-- Section Indicator -->
                <div class="text-center py-2">
                <div class="text-white text-xs font-medium whitespace-nowrap">
                    {{ navSections[currentNavSection]?.name || 'Main' }}
                </div>
                </div>
                
                <!-- Down Arrow -->
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

            <!-- LANGUAGES SECTION -->
            <section id="models" class="py-2 bg-gray-900 rounded-lg mb-16">
                <div class="container mx-auto px-6">
                    <h2 class="text-4xl font-bold text-center mb-12 text-white">
                        Languages I Use
                    </h2>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-8 max-w-6xl mx-auto">
                        <div class="tech-icon bg-gray-800 p-6 rounded-lg text-center hover:bg-gray-700 transition-colors">
                            <div class="w-16 h-16 mx-auto mb-4 bg-red-500 rounded-lg flex items-center justify-center">
                            </div>
                            <h4 class="text-white font-semibold">PHP Laravel</h4>
                        </div>
                        
                        <div class="tech-icon bg-gray-800 p-6 rounded-lg text-center hover:bg-gray-700 transition-colors">
                            <div class="w-16 h-16 mx-auto mb-4 bg-green-500 rounded-lg flex items-center justify-center">
                            </div>
                            <h4 class="text-white font-semibold">Vue.JS</h4>
                        </div>

                        <div class="tech-icon bg-gray-800 p-6 rounded-lg text-center hover:bg-gray-700 transition-colors">
                            <div class="w-16 h-16 mx-auto mb-4 bg-cyan-500 rounded-lg flex items-center justify-center">
                            </div>
                            <h4 class="text-white font-semibold">Symfony</h4>
                        </div>
                        
                        <div class="tech-icon bg-gray-800 p-6 rounded-lg text-center hover:bg-gray-700 transition-colors">
                            <div class="w-16 h-16 mx-auto mb-4 bg-purple-500 rounded-lg flex items-center justify-center">
                            </div>
                            <h4 class="text-white font-semibold">MySQL</h4>
                        </div>
                        
                        <div class="tech-icon bg-gray-800 p-6 rounded-lg text-center hover:bg-gray-700 transition-colors">
                            <div class="w-16 h-16 mx-auto mb-4 bg-blue-500 rounded-lg flex items-center justify-center">
                            </div>
                            <h4 class="text-white font-semibold">TypeScript</h4>
                        </div>

                        <div class="tech-icon bg-gray-800 p-6 rounded-lg text-center hover:bg-gray-700 transition-colors">
                            <div class="w-16 h-16 mx-auto mb-4 bg-yellow-500 rounded-lg flex items-center justify-center">
                            </div>
                            <h4 class="text-white font-semibold">jQuery</h4>
                        </div>
                    </div>
                </div>
            </section>

            <section id="featured" class="py-20 bg-gray-800 rounded-lg mb-16">
                <div class="container mx-auto px-6">
                    <h2 class="text-4xl font-bold text-center mb-12 text-white">User Statistics</h2>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-3">
                            
                            <div class="space-y-6">

                                <div class="space-y-8 bg-gray-700 p-6 rounded-lg shadow-lg">
                                    <section>
                                        <div class="w-full lg:w-1/2 lg:max-w-[50%] overflow-hidden">
                                            <LineChartComponent 
                                            />
                                        </div>
                                    </section>
                                </div>

                                <div class="space-y-8">
                                    <section>
                                        <div class="w-full lg:w-1/2 lg:max-w-[50%] overflow-hidden">
                                            <GameHeatmapComponent 
                                            ref="gameHeatmapRef" 
                                            :gameId="gameId" 
                                            :gameQuestions="gameQuestions" 
                                            />
                                        </div>
                                    </section>
                                </div>

                                <div class="space-y-8">
                                    <section>
                                        <ChartComponent />
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

/* Crossfade transition for images */
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

.hero-gradient-mask {
  mask-image: linear-gradient(to right, transparent 0%, black 50%, black 100%);
  -webkit-mask-image: linear-gradient(to right, transparent 0%, black 50%, black 100%);
}

/* Smooth scrolling for the entire page */
html {
    scroll-behavior: smooth;
}
</style>