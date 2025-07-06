<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import ChartComponent from '@/Components/ChartComponent.vue';
import HeroSection from '@/Components/HeroSection.vue';
import DashboardGameDetails from '@/Components/DashboardGameDetails.vue';
import DashboardAIDetails from '@/Components/DashboardAIDetails.vue';

const showNav = ref(false);

const currentSection = ref(0);

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
const sections = [
    { id: 'main', name: 'Main' },
    { id: 'featured', name: 'Stats' },
    { id: 'game-details', name: 'Games' },
    { id: 'ai-details', name: 'AI' },
    { id: 'nature-collections', name: 'More' }
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

// Section navigation functions
const scrollToSection = (direction) => {
    const newIndex = direction === 'up' 
        ? Math.max(0, currentSection.value - 1)
        : Math.min(sections.length - 1, currentSection.value + 1);

    if (newIndex !== currentSection.value) {
        currentSection.value = newIndex;

        if (newIndex === 0) {
            // Scroll to top for 'main'
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            const element = document.getElementById(sections[newIndex].id);
            if (element) {
                const yOffset = -80; // adjust this to control how far above you want
                const y = element.getBoundingClientRect().top + window.pageYOffset + yOffset;
                window.scrollTo({ top: y, behavior: 'smooth' });
            }
        }
    }
};



const updateCurrentSection = () => {
    const sectionElements = sections.map(section => ({
        element: document.getElementById(section.id),
        index: sections.findIndex(s => s.id === section.id)
    })).filter(item => item.element);

    const scrollPosition = window.scrollY + 200; // Offset for better detection

    for (let i = sectionElements.length - 1; i >= 0; i--) {
        const { element, index } = sectionElements[i];
        if (element.offsetTop <= scrollPosition) {
            currentSection.value = index;
            break;
        }
    }
};

// Auto-advance slideshow every 15 seconds
let slideInterval;
onMounted(() => {
    slideInterval = setInterval(() => {
        nextSlide();
    }, 15000); // Change slide every 15 seconds

    // Add scroll listener for section detection
    window.addEventListener('scroll', updateCurrentSection);
    
    // Initialize current section
    nextTick(() => {
        updateCurrentSection();
    });
});

onUnmounted(() => {
    if (slideInterval) {
        clearInterval(slideInterval);
    }
    window.removeEventListener('scroll', updateCurrentSection);
});
</script>

<template>
    <Head title="Dashboard" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-md text-white leading-tight">Dashboard</h2>
        </template>

        <section id="main">
        </section>

        <!-- Vertical Navigation -->

        <transition name="fade">
            <div
                v-if="showNav"
                class="hidden sm:block fixed py-2 mx-auto mt-12 ml-4 z-0 bg-gray-800/80 backdrop-blur-sm rounded-lg p-2 shadow-lg"
            >
                <button 
                @click="scrollToSection('up')"
                :disabled="currentSection === 0"
                class="block w-8 h-8 mb-2 flex items-center justify-center rounded hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                </svg>
            </button>
            
            <div class="text-center py-2 px-1">
                <span class="text-white font-medium whitespace-nowrap" style="font-size: 11px !important;">
                    {{ sections[currentSection]?.name || 'Loading...' }}
                </span>
            </div>
            
            <button 
                @click="scrollToSection('down')"
                :disabled="currentSection === sections.length - 1"
                class="block w-8 h-8 mt-2 flex items-center justify-center rounded hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            </div>
        </transition>

        <div class="py-12 main-width mx-auto sm:px-6 lg:px-8">
            <section class="mb-16">
                <HeroSection />
            </section>

            <section id="featured" class="mb-16">
                <h2 class="text-3xl font-bold text-blue-600 mb-4 text-center">Featured Content</h2>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2">
                        <div class="relative">
                            <div class="absolute -top-4 -left-4 w-full h-full bg-blue-600 rounded-lg -z-10"></div>
                            <img src="/images/7.jpg" alt="Interior Design" class="w-full h-96 object-cover rounded-lg shadow-lg">
                            <div class="absolute bottom-0 left-0 right-0 theme-bg-primary p-6 rounded-lg mx-4 mb-4 shadow-lg">
                                <h3 class="text-2xl font-bold theme-text-primary mb-2">Simple but very cozy</h3>
                                <p class="theme-text-secondary">Sed rhoncus egestas felis, sit amet condimentum sem ultricies at malesuada, tortor sit amet.</p>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-8">
                        <section>
                            <ChartComponent />
                        </section>
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

<style>

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.fade-enter-to,
.fade-leave-from {
  opacity: 1;
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