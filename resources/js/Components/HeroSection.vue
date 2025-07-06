<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const currentSlide = ref(0);
const slides = [
  {
    src: '/images/vecteezy_domesticated-black-donkeys-in-the-paddock-on-the-farm-pets_49542847.jpg',
    alt: 'AI Object Detection',
    title: 'AI Object Detection',
    description: 'Advanced computer vision technology...',
    button1: { text: 'View Detection Models', href: '/models' },
    button2: { text: 'Try Object Detection Demo', href: '/demo' }
  },
  {
    src: '/images/person-with-futuristic-metaverse-avatar-mask.jpg',
    alt: 'Futuristic Metaverse Avatar',
    title: 'Metaverse Innovation',
    description: 'Immersive virtual experiences powered by avatars...',
    button1: { text: 'Explore Metaverse Avatars', href: '/avatars' },
    button2: { text: 'Join Virtual Demo', href: '/metaverse-demo' }
  }
];

const nextSlide = () => {
  currentSlide.value = (currentSlide.value + 1) % slides.length;
};

const goToSlide = (index) => {
  currentSlide.value = index;
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
  <section class="relative bg-gradient-to-r from-blue-500 to-purple-500 rounded-lg overflow-hidden mb-8">
    <div class="absolute inset-0 bg-black opacity-30"></div>

    <div class="absolute inset-0 flex items-center justify-end">
      <div class="relative w-[50%] h-[400px] overflow-hidden">
        <transition-group name="crossfade" tag="div" class="absolute inset-0">
          <img
            v-for="(slide, index) in slides"
            v-show="index === currentSlide"
            :key="`slide-${index}`"
            :src="slide.src"
            :alt="slide.alt"
            class="absolute inset-0 h-full w-full object-cover hero-gradient-mask"
          />
        </transition-group>
        <button 
          @click="nextSlide"
          class="absolute top-1/2 right-4 z-20 -translate-y-1/2 bg-white/20 hover:bg-white/30 !text-white p-2 rounded-full transition duration-200 backdrop-blur-sm"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="relative z-10 px-6 py-16 sm:px-12 lg:px-16">
      <transition name="fade" appear mode="out-in">
        <div :key="currentSlide" class="max-w-xl">
          <h1 class="text-4xl font-bold !text-white mb-4 font-serif">{{ slides[currentSlide].title }}</h1>
          <p class="text-xl text-gray-200 mb-8">{{ slides[currentSlide].description }}</p>
          <div class="flex flex-col sm:flex-row gap-4">
            <a :href="slides[currentSlide].button1.href" class="bg-black !text-white px-8 py-3 rounded-lg border border-black hover:bg-gray-800 transition">
              {{ slides[currentSlide].button1.text }}
            </a>
            <a :href="slides[currentSlide].button2.href" class="bg-transparent !text-white px-8 py-3 rounded-lg border border-white hover:bg-white/50 hover:text-black transition">
              {{ slides[currentSlide].button2.text }}
            </a>
          </div>
        </div>
      </transition>
    </div>

    <!-- Navigation dots -->
    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex space-x-3 z-20">
      <button 
        v-for="(slide, index) in slides"
        :key="index"
        @click="goToSlide(index)"
        class="w-3 h-3 rounded-full transition hover:scale-110"
        :class="index === currentSlide ? 'bg-white shadow-lg' : 'bg-white/50 hover:bg-white/70'"
      ></button>
    </div>
  </section>
</template>

<style scoped>
.hero-gradient-mask {
  mask-image: linear-gradient(to right, transparent 0%, black 50%, black 100%);
  -webkit-mask-image: linear-gradient(to right, transparent 0%, black 50%, black 100%);
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
</style>
