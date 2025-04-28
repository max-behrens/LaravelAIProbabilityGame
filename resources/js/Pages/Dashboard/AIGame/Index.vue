<script setup>
import { ref, watchEffect, onMounted } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import { useGames } from '@/Composables/useGames';


const props = defineProps({
  games: Array,
  user: Object,
});

const { games: liveGames, user, error, fetchGames } = useGames();

const gamesData = ref([]);
const playersCount = ref({});
const userInGames = ref({});
const errorMessage = ref('');
const successMessage = ref('');
// const router = useRouter();


// Initialize with props and then update with live data
onMounted(() => {
  updateGameState(props.games || []);
  fetchGames(); // Get latest data from API
});

watchEffect(() => {
  if (liveGames.value && liveGames.value.length > 0) {
    updateGameState(liveGames.value);
  }
});

const updateGameState = (gamesList) => {
  if (!Array.isArray(gamesList)) return;
  
  gamesData.value = gamesList;

  
  // Update players count and user participation for each game
  gamesList.forEach(game => {
    playersCount.value[game.id] = game.players_count || 0;
    
    // Check if current user is in this game
    if (game.users) {
      userInGames.value[game.id] = game.users.some(u => u.id === props.user.id);
    } else {
      // Fallback if users not populated
      userInGames.value[game.id] = false;
    }
  });
  
};

const joinGame = async (gameId) => {
  errorMessage.value = '';
  successMessage.value = '';

  try {
    await axios.get('/sanctum/csrf-cookie');
    const response = await axios.post(`/dashboard/games/${gameId}/join`);
    
    if (response.data.success) {
      // Update local state
      userInGames.value[gameId] = true;
      playersCount.value[gameId] = (playersCount.value[gameId] || 0) + 1;
      
      // Refresh games data
      await fetchGames();
      
      successMessage.value = 'Successfully joined the game!';
      
      // Navigate to game room
      window.location.href = `/dashboard/room/${gameId}/${props.user.id}`;
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Error joining game';
    console.error('Error joining game:', err);
  }
};

const leaveGame = async (gameId) => {
  errorMessage.value = '';
  successMessage.value = '';

  try {
    await axios.get('/sanctum/csrf-cookie');
    const response = await axios.post(`/dashboard/games/${gameId}/leave`);
    
    if (response.data.success) {
      // Update local state
      userInGames.value[gameId] = false;
      playersCount.value[gameId] = Math.max(0, (playersCount.value[gameId] || 1) - 1);
      
      // Refresh games data
      await fetchGames();
      
      successMessage.value = 'Successfully left the game!';
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Error leaving game';
    console.error('Error leaving game:', err);
  }
};

const enterGame = (gameId) => {
  window.location.href = `/dashboard/room/${gameId}/${props.user.id}`;
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
        <div v-if="errorMessage" class="mb-4 p-4 bg-red-100 text-red-700 rounded">
          {{ errorMessage }}
        </div>

        <div v-if="successMessage" class="mb-4 p-4 bg-green-100 text-green-700 rounded">
          {{ successMessage }}
        </div>

        <div v-if="gamesData.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="game in gamesData" :key="game.id" class="bg-white shadow-md rounded-lg p-4">
            <h2 class="text-xl text-gray-700 font-semibold mb-2">{{ game.title }}</h2>
            <p class="text-gray-600 mb-2">
              {{ playersCount[game.id] || 0 }} / {{ game.max_players }} players
            </p>
            <p class="text-xs text-gray-500 mb-2">
              Status: {{ userInGames[game.id] ? 'You have joined' : 'Not joined' }}
            </p>

            <div class="flex justify-between">

              <!-- Enter Game Button -->
              <Link
                  class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
                    :href="route('room', { game: game.id, user: props.user.id })"
              >
                  Enter Game
              </Link>

              <!-- Join Game Button -->
              <button
                @click="joinGame(game.id)"
                :disabled="userInGames[game.id] || (playersCount[game.id] >= game.max_players)"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                + Join Game
              </button>

              <!-- Leave Game Button -->
              <button
                @click="leaveGame(game.id)"
                :disabled="!userInGames[game.id]"
                class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
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