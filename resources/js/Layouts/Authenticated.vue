<script setup>
import { ref } from 'vue';
import BreezeApplicationLogo from '@/Components/ApplicationLogo.vue';
import BreezeDropdown from '@/Components/Dropdown.vue';
import BreezeDropdownLink from '@/Components/DropdownLink.vue';
import BreezeNavLink from '@/Components/NavLink.vue';
import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/inertia-vue3';

const showingNavigationDropdown = ref(false);




</script>

<template>
    <div class="bg-gray-900 text-white">
        <div class="min-h-screen bg-gray-900">
            <nav class="bg-gray-800 border-b border-gray-700">
                <!-- Primary Navigation Menu -->
                <div class="nav-header max-w-7xl mx-auto">
                    <div class="flex justify-between h-16">

                                <!-- Logo -->
                                <div class="shrink-0 flex items-center">
                                    <Link :href="route('dashboard')">
                                        <BreezeApplicationLogo class="block h-7 w-auto text-white" />
                                    </Link>
                                </div>
                                <!-- Navigation Links -->
                                <div class="hidden space-x-8 sm:-my-px sm:ml-5 sm:flex">
                                    <BreezeNavLink 
                                        :href="route('dashboard')" 
                                        :active="route().current('dashboard')" 
                                        class="text-xs !text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300"
                                    >
                                        Dashboard
                                    </BreezeNavLink>
                                    <BreezeNavLink 
                                        :href="route('posts.index')" 
                                        :active="route().current('posts.index')" 
                                        class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300"
                                    >
                                        Posts
                                    </BreezeNavLink>
                                    <BreezeNavLink 
                                        :href="route('ai-game')" 
                                        :active="route().current('ai-game')" 
                                        class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300"
                                    >
                                        AI Game
                                    </BreezeNavLink>
                                    <BreezeNavLink 
                                        :href="route('weather.index')" 
                                        :active="route().current('weather.index')" 
                                        class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300"
                                    >
                                        Weather API
                                    </BreezeNavLink>
                                    <BreezeNavLink 
                                        :href="route('parse-xml')" 
                                        :active="route().current('parse-xml')" 
                                        class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300"
                                    >
                                        XML Parser
                                    </BreezeNavLink>
                                    <BreezeNavLink 
                                        :href="route('react.index')" 
                                        :active="route().current('react.index')" 
                                        class="text-xs text-white hover:text-gray-300 focus:text-gray-300 active:text-gray-300"
                                    >
                                        React Page
                                    </BreezeNavLink>
                                </div>

                            <div class="absolute -bottom-5 py-3 text-xs text-gray-600">
                                Max Behrens
                            </div>


                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <!-- Settings Dropdown -->
                            <div class="ml-3 relative">
                                <BreezeDropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-xs leading-4 font-medium rounded-md text-gray-400 bg-gray-800 hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <svg class="ml-2 -mr-0.5 h-4 w-4 text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <BreezeDropdownLink :href="route('logout')" method="post" as="button" class="text-gray-300 hover:text-black">
                                            Log Out
                                        </BreezeDropdownLink>
                                    </template>
                                </BreezeDropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-mr-2 flex items-center sm:hidden">
                            <button @click="showingNavigationDropdown = ! showingNavigationDropdown" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-700 focus:outline-none focus:bg-gray-700 focus:text-gray-500 transition duration-150 ease-in-out">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
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
                            <div class="font-medium text-base text-gray-300">{{ $page.props.auth.user.name }}</div>
                            <div class="font-medium text-sm text-gray-400">{{ $page.props.auth.user.email }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <BreezeResponsiveNavLink :href="route('logout')" method="post" as="button" 
                            class="text-xs text-white hover:text-white !bg-transparent"
                        >
                                Log Out
                            </BreezeResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-gray-800 shadow" v-if="$slots.header">
                <div class="header-title py-6 max-w-7xl mx-auto">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
