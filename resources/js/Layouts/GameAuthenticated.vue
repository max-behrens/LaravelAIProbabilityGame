<script setup>
import { ref, onMounted } from 'vue';
import BreezeApplicationLogo from '@/Components/ApplicationLogo.vue';
import BreezeDropdownLink from '@/Components/DropdownLink.vue';
import { Link } from '@inertiajs/inertia-vue3';

const showingNavigation = ref(false);
const showLogoutModal = ref(false);
const isDark = ref(false);

const toggleNavigation = () => {
  showingNavigation.value = !showingNavigation.value;
};

// Theme management
const initializeTheme = () => {
  // Check if user has a saved preference, otherwise use system preference
  const savedTheme = localStorage.getItem('theme');
  const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  
  isDark.value = savedTheme ? savedTheme === 'dark' : systemPrefersDark;
  applyTheme();
};

const applyTheme = () => {
  if (isDark.value) {
    document.documentElement.classList.add('dark');
    localStorage.setItem('theme', 'dark');
  } else {
    document.documentElement.classList.remove('dark');
    localStorage.setItem('theme', 'light');
  }
};

const toggleTheme = () => {
  isDark.value = !isDark.value;
  applyTheme();
};

onMounted(() => {
  initializeTheme();
});
</script>

<template>
  <div class="bg-gray-900 text-white">
    <div class="min-h-screen bg-gray-900">
      <main class="flex min-h-screen">
        <transition name="slide">
          <nav
            v-if="showingNavigation"
            class="fixed top-0 left-0 h-full w-64 bg-gray-800 border-r border-gray-700 z-50 shadow-lg flex-shrink-0"
          >
            <div class="flex flex-col h-full">
              <div class="flex-1 px-4 py-6 space-y-2 mt-16">
                <Link
                  :href="route('ai-game')"
                  class="flex items-center px-3 py-2 text-sm font-medium text-white hover:text-gray-300 hover:bg-gray-700 rounded-md transition duration-150 ease-in-out"
                  @click="showingNavigation = false"
                >
                  <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                  </svg>
                  AI Game
                </Link>

                <Link
                  :href="isInRoom ? route('room', { game: props.currentGameId, user: $page.props.auth.user.id }) : '#'"
                  :class="{
                    'flex items-center px-3 py-2 text-sm font-medium transition duration-150 ease-in-out rounded-md': true,
                    'text-white hover:text-gray-300 hover:bg-gray-700': isInRoom,
                    'text-gray-500 cursor-not-allowed bg-gray-800': !isInRoom
                  }"
                  :disabled="!isInRoom"
                  @click="isInRoom && (showingNavigation = false)"
                >
                  <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                  Room
                </Link>
              </div>

              <div class="border-t border-gray-700 p-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <div class="flex-shrink-0">
                      <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center">
                        <span class="text-sm font-medium text-white">
                          {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                        </span>
                      </div>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-white">{{ $page.props.auth.user.name }}</p>
                      <p class="text-xs text-gray-400">{{ $page.props.auth.user.email }}</p>
                    </div>
                  </div>
                  <button
                    @click="showLogoutModal = true"
                    class="p-1 rounded-md text-gray-400 hover:text-white hover:bg-gray-700"
                  >
                  <svg
                    class="h-4 w-4 text-gray-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path d="M19.14 12.94a7.002 7.002 0 000-1.88l2.03-1.58a.5.5 0 00.12-.64l-1.92-3.32a.5.5 0 00-.6-.22l-2.39.96a6.978 6.978 0 00-1.6-.94l-.36-2.54A.5.5 0 0014 3h-4a.5.5 0 00-.49.42l-.36 2.54a6.978 6.978 0 00-1.6.94l-2.39-.96a.5.5 0 00-.6.22l-1.92 3.32a.5.5 0 00.12.64l2.03 1.58a7.002 7.002 0 000 1.88l-2.03 1.58a.5.5 0 00-.12.64l1.92 3.32a.5.5 0 00.6.22l2.39-.96c.5.38 1.04.7 1.6.94l.36 2.54A.5.5 0 0010 21h4a.5.5 0 00.49-.42l.36-2.54c.56-.24 1.1-.56 1.6-.94l2.39.96a.5.5 0 00.6-.22l1.92-3.32a.5.5 0 00-.12-.64l-2.03-1.58zM12 15.5a3.5 3.5 0 110-7 3.5 3.5 0 010 7z" />
                  </svg>
                  </button>
                </div>
              </div>
            </div>
          </nav>
        </transition>

        <!-- Version that moves content when navbar opens. -->
        <!-- <div
          class="flex-1 flex flex-col transition-all duration-300 ease-in-out"
          :class="{ 'ml-64': showingNavigation }"
        > -->
        <div
          class="flex-1 flex flex-col transition-all duration-300 ease-in-out"
        >

          <div class="flex-1 pt-4">
            <button
              @click="toggleNavigation"
              class="game-menu-width 
                    inline-flex items-center justify-center p-2 
                    rounded-md text-gray-400 hover:text-white 
                    hover:bg-gray-700 focus:outline-none 
                    focus:bg-gray-700 focus:text-white 
                    transition duration-150 ease-in-out ml-4"
            >
              <svg class="h-6 w-6 mr-2" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path
                  :class="{ hidden: showingNavigation, 'inline-flex': !showingNavigation }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"
                />
                <path
                  :class="{ hidden: !showingNavigation, 'inline-flex': showingNavigation }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
              Game Menu
            </button>

            <div> <slot />
            </div>
          </div>
        </div>

        <transition name="overlay">
          <div
            v-if="showingNavigation"
            class="fixed inset-0 bg-black bg-opacity-50 z-40"
            @click="showingNavigation = false"
          ></div>
        </transition>
      </main>

      <transition name="fade">
        <div
          v-if="showLogoutModal"
          class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50"
          @click.self="showLogoutModal = false"
        >
          <div class="bg-gray-900 rounded-lg shadow-lg p-6 w-80 max-w-full text-gray-200">
            <h2 class="text-lg font-semibold mb-4">User Options</h2>

            <div class="flex flex-col space-y-4">

              <!-- Theme Toggle Button -->
              <button
                type="button"
                class="w-full px-4 py-2 rounded bg-gray-700 hover:bg-gray-600 text-left text-white flex items-center justify-between"
                @click="toggleTheme"
              >
                <span>{{ isDark ? 'Light Mode' : 'Dark Mode' }}</span>
                <svg 
                  class="h-5 w-5" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                  v-if="isDark"
                >
                  <!-- Sun icon for light mode -->
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <svg 
                  class="h-5 w-5" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                  v-else
                >
                  <!-- Moon icon for dark mode -->
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                </svg>
              </button>

              <button
                type="button"
                class="w-full px-4 py-2 rounded bg-gray-700 hover:bg-gray-600 text-left transition duration-150 ease-in-out"
                @click="showLogoutModal = false"
              >
                Settings
              </button>

              <BreezeDropdownLink
                :href="route('logout')"
                method="post"
                as="button"
                class="w-full px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white transition duration-150 ease-in-out"
                @click="showLogoutModal = false"
              >
                Log Out
              </BreezeDropdownLink>
            </div>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.overlay-enter-active,
.overlay-leave-active {
  transition: opacity 0.3s ease;
}
.overlay-enter-from,
.overlay-leave-to {
  opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(-100%);
}
</style>