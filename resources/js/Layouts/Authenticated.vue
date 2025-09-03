<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { Link } from '@inertiajs/inertia-vue3';
import { Inertia } from '@inertiajs/inertia';
import BreezeApplicationLogo from '@/Components/ApplicationLogo.vue';
import BreezeWebsiteLogo from '@/Components/WebsiteLogo.vue';
import BreezeDropdown from '@/Components/Dropdown.vue';
import BreezeDropdownLink from '@/Components/DropdownLink.vue';
import BreezeNavLink from '@/Components/NavLink.vue';
import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';


const showingNavigationDropdown = ref(false);
const showLogoutModal = ref(false);
const isDark = ref(false);
const isScrolled = ref(false);
const navigationDropdownRef = ref(null);


const logout = () => {
  showLogoutModal.value = false;
  Inertia.post(route('logout'), {}, {
    onSuccess: () => {
      Inertia.visit(route('login'));
    }
  });
};


const handleClickOutside = (event) => {
  if (navigationDropdownRef.value && !navigationDropdownRef.value.contains(event.target)) {
    showingNavigationDropdown.value = false;
  }
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

// Handle scroll for navbar color change
const handleScroll = () => {
  const featuredSection = document.getElementById('featured');
  if (featuredSection) {
    const featuredTop = featuredSection.offsetTop;
    const scrollPosition = window.scrollY + 100; // Add offset for better detection
    
    isScrolled.value = scrollPosition >= featuredTop;
  }
};

onMounted(() => {
  initializeTheme();

  // Add scroll listener
  window.addEventListener('scroll', handleScroll);

    document.addEventListener('click', handleClickOutside);

  
  // Initial check
  handleScroll();
});

// Clean up scroll listener when component unmounts
onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleScroll);
    document.removeEventListener('click', handleClickOutside);

});
</script>

<template>
  <div class="bg-gray-900 text-white min-h-screen flex flex-col">
    <div class="bg-gray-900 flex-grow flex flex-col">
      <!-- Sticky Navigation with dynamic background -->
      <nav 
        class="fixed top-0 left-0 right-0 z-50 border-b border-gray-700 transition-all duration-300 h-16"
        :class="isScrolled ? 'bg-gray-700 backdrop-blur-md' : 'bg-gray-800'"
      >
        <!-- Primary Navigation Menu -->
         <div class="flex flex-row justify-between">
            <div class="main-width mx-auto px-4 lg:px-6">
              <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="shrink-0 flex items-center mr-2">
                    <BreezeWebsiteLogo :href="route('dashboard')" class="block h-7 w-auto" />
                </div>
                
                <!-- Navigation Links - Centered -->
                <div class="hidden lg:flex absolute left-1/2 transform -translate-x-1/2">
                  <div class="flex space-x-8 h-16 items-center">
                    <BreezeNavLink
                      :href="route('dashboard')"
                      :active="route().current('dashboard')"
                      class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300 h-full flex items-center relative"
                    >
                      Dashboard
                    </BreezeNavLink>
                    <BreezeNavLink
                      :href="route('posts.index')"
                      :active="route().current('posts.index')"
                      class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300 h-full flex items-center relative"
                    >
                      Posts
                    </BreezeNavLink>
                    <BreezeNavLink
                      :href="route('ai-game')"
                      :active="route().current('ai-game')"
                      class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300 h-full flex items-center relative"
                    >
                      AI Game
                    </BreezeNavLink>
                    <BreezeNavLink
                      :href="route('weather.index')"
                      :active="route().current('weather.index')"
                      class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300 h-full flex items-center relative"
                    >
                      Weather API
                    </BreezeNavLink>
                    <BreezeNavLink
                      :href="route('parse-xml')"
                      :active="route().current('parse-xml')"
                      class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300 h-full flex items-center relative"
                    >
                      XML Parser
                    </BreezeNavLink>
                    <BreezeNavLink
                      :href="route('react.index')"
                      :active="route().current('react.index')"
                      class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300 h-full flex items-center relative"
                    >
                      React Page
                    </BreezeNavLink>
                  </div>
                </div>
              </div>
            </div>
            
              <!-- User Section - Fixed width to balance logo -->
              <div class="main-width px-4 hidden lg:flex lg:items-center justify-end">
                <!-- User Name Display -->
                <div class="text-sm text-gray-300">
                  {{ $page.props.auth?.user?.name || 'Loading...' }}
                </div>
                
                <!-- Settings button opens modal directly -->
                <button
                  @click="showLogoutModal = true"
                  type="button"
                  class="inline-flex items-center px-3 border border-transparent text-xs leading-4 font-medium rounded-md text-gray-400 hover:text-gray-300 focus:outline-none"
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
            <!-- Hamburger -->
            <div class="main-width px-6 flex items-center lg:hidden">
              <button
                @click="showingNavigationDropdown = !showingNavigationDropdown"
                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-700 focus:outline-none focus:bg-gray-700 focus:text-gray-500 transition duration-50 ease-in-out"
              >
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                  <path
                    :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                  <path
                    :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>


        <!-- Responsive Navigation Menu -->
        <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="lg:hidden bg-gray-800 relative z-50">
          <div class="pt-2 pb-3 space-y-1">
            <BreezeResponsiveNavLink
              :href="route('dashboard')"
              :active="route().current('dashboard')"
              class="text-xs text-white !bg-transparent hover:text-white focus:text-white active:text-white"
            >
              Dashboard
            </BreezeResponsiveNavLink>
            <BreezeResponsiveNavLink
              :href="route('posts.index')"
              :active="route().current('posts.index')"
              class="text-xs text-white !bg-transparent hover:text-white focus:text-white active:text-white"
            >
              Posts
            </BreezeResponsiveNavLink>
            <BreezeResponsiveNavLink
              :href="route('ai-game')"
              :active="route().current('ai-game')"
              class="text-xs text-white !bg-transparent hover:text-white focus:text-white active:text-white"
            >
              AI Game
            </BreezeResponsiveNavLink>
            <BreezeResponsiveNavLink
              :href="route('weather.index')"
              :active="route().current('weather.index')"
              class="text-xs text-white !bg-transparent hover:text-white focus:text-white active:text-white"
            >
              Weather API
            </BreezeResponsiveNavLink>
            <BreezeResponsiveNavLink
              :href="route('parse-xml')"
              :active="route().current('parse-xml')"
              class="text-xs text-white !bg-transparent hover:text-white focus:text-white active:text-white"
            >
              XML Parser
            </BreezeResponsiveNavLink>
            <BreezeResponsiveNavLink
              :href="route('react.index')"
              :active="route().current('react.index')"
              class="text-xs text-white !bg-transparent hover:text-white focus:text-white active:text-white"
            >
              React Page
            </BreezeResponsiveNavLink>
          </div>

          <!-- Responsive Settings Options -->
          <div class="pt-4 pb-1 border-t border-gray-700">
            <div class="px-4">
              <div class="font-medium text-base text-gray-300">
                {{ $page.props.auth?.user?.name || 'Loading...' }}
              </div>
              <div class="font-medium text-sm text-gray-400">
                {{ $page.props.auth?.user?.email || '' }}
              </div>

              <div class="mt-3 mb-3 space-y-1">
                <button
                  type="button"
                  class="px-2 py-2 rounded bg-red-600 hover:bg-red-700 text-white"
                  @click="logout"
                >
                  Log Out
                </button>
              </div>
            </div>
          </div>
          
        </div>
      </nav>

      <!-- Add padding to account for fixed navbar -->
      <div class="pt-16">
        <!-- Page Heading -->
        <!-- <header class="bg-gray-800 shadow" v-if="$slots.header">
            <div class="main-width py-6">
            <slot name="header" />
          </div>
        </header> -->

        <!-- Main content area - this will grow to fill available space -->
        <main class="">
          <slot />
        </main>
      </div>

      <!-- Footer - will stick to bottom -->
    <footer class="bg-gray-800 border-t border-gray-700 text-gray-400 text-xs py-4 mt-auto">
      <div>
        <div class="main-width mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-center gap-4">
          <span>Max Behrens</span>
          <BreezeApplicationLogo class="block h-7 w-auto text-white" />
        </div>
      </div>
    </footer>


      <!-- Logout / Settings Modal -->
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
                class="w-full px-4 py-2 rounded bg-gray-700 hover:bg-gray-600 text-left text-white"
                @click="() => {}"
              >
                Settings
              </button>

              <button
                type="button"
                class="w-full px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white"
                @click="logout"
              >
                Log Out
              </button>

            </div>
          </div>
        </div>
      </transition>

      <transition name="overlay">
        <div
          v-if="showingNavigationDropdown"
          class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden"
          @click="showingNavigationDropdown = false"
        ></div>
      </transition>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.1s ease;
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
</style>