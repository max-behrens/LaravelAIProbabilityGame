<script setup>
import { Link } from '@inertiajs/inertia-vue3';
import { computed } from 'vue';

const props = defineProps({
  links: {
    type: Array,
    required: true,
  },
});

const firstLink = computed(() => {
  return props.links.find(link => link.label === '1');
});

const lastLink = computed(() => {
  return props.links.find(link => link.label === String(props.links.length - 2));
});

const prevUrl = computed(() => {
  const prev = props.links.find((link) => link.label === '« Previous');
  return prev ? prev.url : null;
});

const nextUrl = computed(() => {
  const next = props.links.find((link) => link.label === 'Next »');
  return next ? next.url : null;
});

const isFirstPage = computed(() => {
  return firstLink.value && firstLink.value.active;
});

const isLastPage = computed(() => {
  return lastLink.value && lastLink.value.active;
});
</script>

<template>
  <div v-if="links.length > 3" class="flex justify-center mt-4">
    <div class="flex flex-wrap -mb-1 items-center">
      <!-- First Button -->
      <Link
        v-if="links.length"
        :href="firstLink ? firstLink.url : null"
        class="mb-1 mr-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white/10 text-gray-300"
        :class="{ 'text-gray-400 pointer-events-none': isFirstPage }"
      >
        First
      </Link>

      <!-- Previous Button -->
      <Link
        v-if="prevUrl"
        :href="prevUrl"
        class="mb-1 mr-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white/10 text-gray-300"
        :class="{ 'text-gray-400 pointer-events-none': isFirstPage }"
      >
        Previous
      </Link>

      <!-- Page Links -->
      <template v-for="(link, key) in links">
        <div
          v-if="link.url === null"
          :key="key"
          class="mb-1 mr-1 px-4 py-3 text-gray-400 text-sm leading-4 border rounded"
          v-html="link.label"
        />
        <Link
          v-else
          :key="`link-${key}`"
          class="mb-1 mr-1 px-4 py-3 focus:text-indigo-500 text-sm leading-4 hover:bg-white/10 border focus:border-indigo-500 rounded"
          :class="{ 'bg-gray-800 text-white': link.active }"
          :href="link.url"
          v-html="link.label"
        />
      </template>

      <!-- Next Button -->
      <Link
        v-if="nextUrl"
        :href="nextUrl"
        class="mb-1 mr-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white/10 text-gray-300"
        :class="{ 'text-gray-400 pointer-events-none': isLastPage }"
      >
        Next
      </Link>

      <!-- Last Button -->
      <Link
        v-if="links.length"
        :href="lastLink ? lastLink.url : null"
        class="mb-1 mr-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white/10 text-gray-300"
        :class="{ 'text-gray-400 pointer-events-none': isLastPage }"
      >
        Last
      </Link>
    </div>
  </div>
</template>