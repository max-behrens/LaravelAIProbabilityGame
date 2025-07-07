<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const currentSlide = ref(0);
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

const nextSlide = () => {
  currentSlide.value = (currentSlide.value + 1) % slides.length;
};

const goToSlide = (index) => {
  currentSlide.value = index;
};

const scrollToFeatured = () => {
  const featuredSection = document.getElementById('featured');
  if (featuredSection) {
    const yOffset = -80;
    const y = featuredSection.getBoundingClientRect().top + window.pageYOffset + yOffset;
    window.scrollTo({ top: y, behavior: 'smooth' });
  }
};

let slideInterval;
onMounted(() => {
  slideInterval = setInterval(nextSlide, 15000);
});
onUnmounted(() => {
  clearInterval(slideInterval);
});
</script>

<template>
  <section class="min-h-screen flex items-center justify-center math-bg">
    <!-- Background slideshow -->
    <div class="absolute inset-0 z-0">
      <transition-group name="crossfade" tag="div" class="absolute inset-0">
        <div
          v-for="(slide, index) in slides"
          v-show="index === currentSlide"
          :key="`slide-${index}`"
          class="absolute inset-0 bg-cover bg-center"
          :style="{ backgroundImage: `url(${slide.src})` }"
        ></div>
      </transition-group>
      <!-- Overlay for better text readability -->
      <div class="absolute inset-0 bg-black opacity-40"></div>
    </div>

    <!-- Content -->
    <div class="text-center z-10 px-6">
      <transition name="fade" appear mode="out-in">
        <div :key="currentSlide" class="max-w-4xl mx-auto">
          <h1 class="text-5xl md:text-7xl font-bold mb-6 !text-white" style="font-size: 20pt !important;">
            {{ slides[currentSlide].title }}
          </h1>
          <div class="text-xl md:text-2xl text-blue-200 mb-8">
            <span class="typewriter" style="font-size: 15pt !important;">{{ slides[currentSlide].description }}</span>
          </div>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a :href="slides[currentSlide].button1.href" 
               class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-large rounded-lg transition-colors">
              {{ slides[currentSlide].button1.text }}
            </a>
            <a :href="slides[currentSlide].button2.href" 
               class="inline-flex items-center px-6 py-3 bg-transparent !text-black border border-white bg-white/70 hover:bg-white hover:text-slate-900 font-large rounded-lg transition-colors">
              {{ slides[currentSlide].button2.text }}
            </a>
          </div>
        </div>
      </transition>
    </div>

    <!-- Navigation dots -->
    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 flex space-x-3 z-20">
      <button 
        v-for="(slide, index) in slides"
        :key="index"
        @click="goToSlide(index)"
        class="w-3 h-3 rounded-full transition-all hover:scale-110"
        :class="index === currentSlide ? 'bg-white shadow-lg' : 'bg-white/50 hover:bg-white/70'"
      ></button>
    </div>

    <!-- Next button -->
    <button 
      @click="nextSlide"
      class="absolute top-1/2 right-4 z-20 -translate-y-1/2 bg-white/20 hover:bg-white/30 !text-white p-3 rounded-full transition-all duration-300 backdrop-blur-sm hover:scale-110"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
    </button>

    <button 
      @click="scrollToFeatured"
      class="absolute bottom-8 left-8 z-20 bg-white/20 hover:bg-white/30 !text-white p-4 rounded-full transition-all duration-300 backdrop-blur-sm hover:scale-110 group animate-bounce"
    >
      <svg class="w-6 h-6 group-hover:translate-y-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
      </svg>
    </button>
  </section>
</template>

<style scoped>
.math-bg {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  background-image: 
    radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 40% 40%, rgba(120, 119, 198, 0.2) 0%, transparent 50%);
}

.typewriter {
  border-right: 2px solid #fff;
  animation: blink 1s infinite;
}

@keyframes blink {
  0%, 50% { border-color: #fff; }
  51%, 100% { border-color: transparent; }
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
  0%, 20%, 53%, 80%, 100% {
    transform: translate3d(0,0,0);
  }
  40%, 43% {
    transform: translate3d(0, -8px, 0);
  }
  70% {
    transform: translate3d(0, -4px, 0);
  }
  90% {
    transform: translate3d(0, -2px, 0);
  }
}

.animate-bounce {
  animation: bounce 2s infinite;
}
</style>