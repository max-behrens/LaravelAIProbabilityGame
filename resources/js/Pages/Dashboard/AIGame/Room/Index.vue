<script setup>
import { ref, defineProps, onMounted, watchEffect } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import GameGraphComponent from '@/Components/GameGraphComponent.vue';
import { useGames } from '@/Composables/useGames';

const props = defineProps({
  gameId: String,
  userId: String,
});

const { games: liveGames, fetchGames } = useGames();

const playerCount = ref("1");
const playAgainstAI = ref(false);
const playersCount = ref(0);
const userInGame = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

// Watch for game state updates
watchEffect(() => {
  const game = liveGames.value.find(g => g.id === parseInt(props.gameId));
  if (game) {
    playersCount.value = game.players_count || 0;
    userInGame.value = game.users?.some(u => u.id === parseInt(props.userId)) || false;
  }
});

const handlePlay = () => {
  console.log(`Playing with ${playerCount.value} player(s), against AI: ${playAgainstAI.value}`);
};

const joinGame = async () => {
  errorMessage.value = '';
  successMessage.value = '';
  try {
    await axios.get('/sanctum/csrf-cookie');
    const response = await axios.post(`/dashboard/games/${props.gameId}/join`);
    if (response.data.success) {
      successMessage.value = 'Successfully joined the game!';
      await fetchGames();
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Error joining game';
    console.error('Error joining game:', err);
  }
};

const leaveGame = async () => {
  errorMessage.value = '';
  successMessage.value = '';
  try {
    await axios.get('/sanctum/csrf-cookie');
    const response = await axios.post(`/dashboard/games/${props.gameId}/leave`);
    if (response.data.success) {
      successMessage.value = 'Successfully left the game!';
      await fetchGames();
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Error leaving game';
    console.error('Error leaving game:', err);
  }
};

const exitGame = () => {
  window.location.href = `/dashboard/aigame`;
};
</script>

<template>
  <Head title="AI Game Room" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-md text-white leading-tight">AI Game Room</h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div v-if="errorMessage" class="mb-4 p-4 bg-red-100 text-red-700 rounded">
          {{ errorMessage }}
        </div>

        <div v-if="successMessage" class="mb-4 p-4 bg-green-100 text-green-700 rounded">
          {{ successMessage }}
        </div>

        <div class="flex flex-wrap gap-6 justify-center items-start">

          <!-- GameGraphComponent -->
          <div class="basis-[20rem] flex-grow p-4 bg-gray-800 rounded shadow">
            <GameGraphComponent />
          </div>

          <!-- Player Table -->
          <div class="basis-[20rem] flex-grow p-4 bg-gray-800 rounded shadow overflow-y-auto">
            <h3 class="font-semibold text-lg mb-2 text-white">Player Scores</h3>
            <table class="w-full text-left border-collapse text-white">
              <thead>
                <tr class="bg-gray-700">
                  <th class="p-2 border-b">Player</th>
                  <th class="p-2 border-b">Score</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="player in players" :key="player.name">
                  <td class="p-2 border-b">{{ player.name }}</td>
                  <td class="p-2 border-b">{{ player.score }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Bottom Controls (Dropdown, Checkbox, Button) -->
          <div class="basis-full flex flex-wrap gap-4 justify-center p-4 bg-gray-800 rounded shadow">

            <!-- Dropdown -->
            <div class="flex items-center gap-2">
              <label for="players" class="font-medium text-white">Number of Players:</label>
              <select id="players" v-model="playerCount" class="border rounded px-2 py-1 bg-gray-700 text-white">
                <option value="1">1 Player</option>
                <option value="2">2 Players</option>
              </select>
            </div>

            <!-- Checkbox -->
            <div class="flex items-center text-white">
              <input type="checkbox" v-model="playAgainstAI" class="mr-2">
              <span>Play against AI</span>
            </div>

            <!-- Play button -->
            <div>
              <button @click="handlePlay" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Play
              </button>
            </div>

            <!-- Join/Leave/Exit Buttons -->
            <div class="flex flex-wrap gap-4 justify-center mt-4 w-full">

              <!-- Exit Game Button -->
              <Link
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
                :href="route('ai-game')"
              >
                Exit Game
              </Link>

              <!-- Join Game Button -->
              <button
                @click="joinGame"
                :disabled="userInGame"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Join Game
              </button>

              <!-- Leave Game Button -->
              <button
                @click="leaveGame"
                :disabled="!userInGame"
                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Leave Game
              </button>

            </div>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>
