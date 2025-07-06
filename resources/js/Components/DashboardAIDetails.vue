<script setup>
import { ref } from 'vue';

const tabs = ref([
  {
    title: 'Collection A',
    items: [
      { src: '/images/collection1.jpg', alt: 'Collection A1' },
      { src: '/images/collection2.jpg', alt: 'Collection A2' },
      { src: '/images/collection3.jpg', alt: 'Collection A3' }
    ]
  },
  {
    title: 'Collection B',
    items: [
      { src: '/images/collection4.jpg', alt: 'Collection B1' },
      { src: '/images/collection5.jpg', alt: 'Collection B2' },
      { src: '/images/collection6.jpg', alt: 'Collection B3' }
    ]
  },
  {
    title: 'Collection C',
    items: [
      { src: '/images/collection7.jpg', alt: 'Collection C1' },
      { src: '/images/collection8.jpg', alt: 'Collection C2' },
      { src: '/images/collection9.jpg', alt: 'Collection C3' }
    ]
  }
]);

const currentTab = ref(0);
</script>

<template>
  <section class="theme-bg-secondary rounded-lg p-8 mb-16">
    <div class="mb-6 border-b border-gray-300 flex space-x-4 overflow-x-auto">
      <button
        v-for="(tab, index) in tabs"
        :key="index"
        @click="currentTab = index"
        :class="[
          'py-2 px-4 rounded-t',
          currentTab === index ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-blue-600'
        ]"
      >
        {{ tab.title }}
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div 
        v-for="(item, i) in tabs[currentTab].items" 
        :key="i"
        class="h-48 bg-gray-300 rounded-lg overflow-hidden"
      >
        <img :src="item.src" :alt="item.alt" class="w-full h-full object-cover">
      </div>
    </div>
  </section>
</template>
