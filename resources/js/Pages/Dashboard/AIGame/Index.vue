<script setup>
import { ref, watchEffect } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { useGames } from '@/Composables/useGames';

const { games, error, fetchGames } = useGames();
const playersCount = ref({});
const errorMessage = ref('');
const successMessage = ref('');

// Fetch games initially when the component mounts
watchEffect(() => {
  if (games.value.length === 0) {
    fetchGames();
  }
});

watchEffect(() => {
  if (games.value) {
    games.value.forEach(game => {
      playersCount.value[game.id] = game.players_count;
    });
  }
});

const isProduction = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';

// Base URL that depends on the environment (local or production)
const baseUrl = isProduction ? import.meta.env.VITE_APP_URL : 'http://localhost:8000';

const joinGame = async (gameId) => {
  errorMessage.value = '';
  successMessage.value = '';

  try {
    // Step 1: Get CSRF cookie
    await axios.get(`${baseUrl}/sanctum/csrf-cookie`, { withCredentials: true });

    // Step 2: Make the POST request to join
    await axios.post(`${baseUrl}/dashboard/games/${gameId}/join`, {}, {
      withCredentials: true,
    });

    // Step 3: Optionally update count or re-fetch data
    await fetchGames();
    successMessage.value = 'Successfully joined the game!';
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Error joining game';
  }
};

const leaveGame = async (gameId) => {
  errorMessage.value = '';
  successMessage.value = '';

  try {
    await axios.get(`${baseUrl}/sanctum/csrf-cookie`, { withCredentials: true });
    await axios.post(`${baseUrl}/dashboard/games/${gameId}/leave`, {}, {
      withCredentials: true,
    });

    await fetchGames();
    successMessage.value = 'Successfully left the game!';
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Error leaving game';
  }
};


</script>

<template>
  <Head title="AI Game Dashboard" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-md text-white leading-tight">AI Game Lobby</h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Flash-style messages -->
        <div v-if="errorMessage" class="mb-4 p-4 bg-red-100 text-red-700 rounded">
          {{ errorMessage }}
        </div>

        <div v-if="successMessage" class="mb-4 p-4 bg-green-100 text-green-700 rounded">
          {{ successMessage }}
        </div>

        <!-- Game Cards -->
        <div v-if="games.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="game in games"
            :key="game.id"
            class="bg-white shadow-md rounded-lg p-4"
          >
            <h2 class="text-xl text-gray-700 font-semibold mb-2">{{ game.title }}</h2>
            <p class="text-gray-600 mb-2">
              {{ playersCount[game.id] }} / {{ game.max_players }} players
            </p>
            <div class="flex justify-between">
              <button
                @click="joinGame(game.id)"
                class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded"
              >
                Join
              </button>
              <button
                @click="leaveGame(game.id)"
                class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded"
              >
                Leave
              </button>
            </div>
          </div>
        </div>

        <div v-else class="text-gray-500 text-center mt-6">
          Loading games or none available.
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
