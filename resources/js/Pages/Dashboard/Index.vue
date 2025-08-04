<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, router, usePage } from '@inertiajs/inertia-vue3';
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import DashboardHeroSection from '@/Components/DashboardHeroSection.vue';
import DashboardChartSection from '@/Components/DashboardChartSection.vue';
import DashboardGameDetails from '@/Components/DashboardGameDetails.vue';
import DashboardAIDetails from '@/Components/DashboardAIDetails.vue';
import '@vuepic/vue-datepicker/dist/main.css';


// Get initial URL parameters
const page = usePage();
const auth = page.props.auth;

console.log('Auth object in Vue component:', auth);
console.log('Auth user in Vue component:', auth?.user);
console.log('Auth user name in Vue component:', auth?.user?.name);

const showNav = ref(false);
const currentSection = ref(0);
const showVerticalNav = ref(false);
const currentNavSection = ref(0);
const isNavigatingProgrammatically = ref(false);


watch(currentSection, (newVal) => {
    showNav.value = newVal !== 0;
});


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
    isNavigatingProgrammatically.value = true; 

    const newIndex = direction === 'up'
        ? Math.max(0, currentNavSection.value - 1)
        : Math.min(navSections.length - 1, currentNavSection.value + 1);

    // Update currentNavSection immediately for instant feedback
    currentNavSection.value = newIndex; 
    
    scrollToSection(newIndex);
};

const scrollToSection = (sectionIndex) => {
    const section = navSections[sectionIndex];
    const targetElement = document.getElementById(section.id);

    if (targetElement) {
        const yOffset = -80;
        const y = targetElement.getBoundingClientRect().top + window.pageYOffset + yOffset;
        
        window.scrollTo({ 
            top: y, 
            behavior: 'smooth' 
        });

        // Use requestAnimationFrame for more precise scroll end detection
        // or a slightly longer timeout if that's too complex.
        // For now, let's keep a robust timeout for simplicity.
        setTimeout(() => {
            isNavigatingProgrammatically.value = false;
            // Force an update to showVerticalNav after scroll is likely finished
            // This ensures it correctly hides if at the top.
            updateNavigation(); 
        }, 800); // Adjust timeout based on your scroll behavior duration
    } else if (section.id === 'main') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
            isNavigatingProgrammatically.value = false;
            updateNavigation(); // Ensure visibility is updated for 'main' section
        }, 800);
    }
};

const updateNavigation = () => {
    const scrollY = window.scrollY;
    const windowHeight = window.innerHeight;

    // This part should *always* update, regardless of programmatic navigation
    showVerticalNav.value = scrollY > windowHeight * 0.3;

    // Only update currentNavSection if not navigating programmatically
    if (isNavigatingProgrammatically.value) {
        return; // Exit here to prevent flickering of currentNavSection name
    }

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
    
    // Fetch users on mount (assuming fetchUsers is defined elsewhere in your script)
    // await fetchUsers(); 

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
            <DashboardHeroSection />
        </section>

          <transition name="fade">
            <div
            v-if="showVerticalNav"
            class="fixed left-4 top-1/2 transform -translate-y-1/2 z-50 bg-gray-800 backdrop-blur-sm rounded-lg p-2 shadow-lg hidden sm:block"
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

        <div class="py-2 mx-auto px-100 lg:px-100">

            <section id="stats">
              <DashboardChartSection />
            </section>
        
            
            <section id="game-details" class="mt-16">
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
                        Test text.
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
/* Your existing styles */
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