<script setup>
import { ref, defineProps, computed, watchEffect, onMounted } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import { useGames } from '@/Composables/useGames';
import DynamicPagination from '@/Components/DynamicPagination.vue';
import GameGraphComponent from '@/Components/GameGraphComponent.vue';
import axios from 'axios';

const props = defineProps({
  gameId: String,
  userId: String,
  gameQuestion: String,
});

// Set up composables and state
const { games: liveGames, fetchGames, fetchGameScores, gameScores, scoresMetadata } = useGames();

console.log('gameScores:', gameScores.value);

console.log('fetchGameScores:', fetchGameScores);



const gameGraphRef = ref(null);

const playerCount = ref("1");
const playAgainstAI = ref(false);
const userInGame = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const currentQuestion = ref(null);
const isGameStarted = ref(false);
const userAnswer = ref('');
const currentGame = ref(null);

// Score pagination
const scoresCurrentPage = ref(1);
const scoresPerPage = ref(10);
const scoresTotalPages = computed(() => scoresMetadata.value?.lastPage || 1);

// Initialize with props and then update with live data
onMounted(() => {
  fetchGames(); // Get latest data from API
  fetchGameScores(props.gameId, scoresCurrentPage.value); // Get scores with pagination
});

// Watch for game updates
watchEffect(() => {
  if (liveGames.value && liveGames.value.length > 0) {
    updateGameState(liveGames.value);
    
    // Find current game in the list
    currentGame.value = liveGames.value.find(game => game.id.toString() === props.gameId);
  }
});

// Update local game state from the list of games
const updateGameState = (gamesList) => {
  if (!Array.isArray(gamesList)) return;
  
  gamesList.forEach(game => {
    // Check if current user is in this game
    if (game.users && game.id.toString() === props.gameId) {
      userInGame.value = game.users.some(u => u.id === parseInt(props.userId));
    }
  });
};

// Handle score pagination
const changeScoresPage = (page) => {
  if (page >= 1 && page <= scoresTotalPages.value) {
    scoresCurrentPage.value = page;
    fetchGameScores(props.gameId, page); // Fetch scores for the new page
  }
};

// Functions for managing game participation
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

const startGame = async () => {
  currentQuestion.value = props.gameQuestion.question;
  isGameStarted.value = true;
};

const submitAnswer = async (answer) => {
  try {
    console.log('gameId:', props.gameId);
    const response = await axios.post('/submit-answer', {
      gameId: props.gameId,
      answer,
    });

    if (response.data.success) {
      currentQuestion.value = null;
      isGameStarted.value = false;
      successMessage.value = response.data.message;

      // Refresh scores with current pagination
      await fetchGameScores(props.gameId, scoresCurrentPage.value);
      await gameGraphRef.value?.fetchPlayerAverages?.();
    }
  } catch (error) {
    console.error('Error submitting answer:', error);
    errorMessage.value = 'Failed to submit answer.';
  }
};

const submit = () => {
  submitAnswer(userAnswer.value);
  userAnswer.value = '';
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  
  const date = new Date(dateString);
  
  // Use Intl.DateTimeFormat for localized date formatting
  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric', 
    month: 'short', 
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  }).format(date);
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

        <!-- Flash Messages -->
        <div v-if="errorMessage" class="mb-4 p-4 bg-red-100 text-red-700 rounded">
          {{ errorMessage }}
        </div>
        <div v-if="successMessage" class="mb-4 p-4 bg-green-100 text-green-700 rounded">
          {{ successMessage }}
        </div>

        <div class="flex flex-wrap gap-6 justify-center items-start">

          <!-- Question Area -->
          <div v-if="isGameStarted" class="basis-full text-center mb-6">
            <p class="text-white mb-4">{{ currentQuestion }}</p>
            <input v-model="userAnswer" @keyup.enter="submit" class="px-4 py-2 rounded text-black" placeholder="Your Answer" />
            <button @click="submit" class="ml-2 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
              Submit Answer
            </button>
          </div>

          <!-- Controls -->
          <div class="basis-full flex flex-wrap gap-4 justify-center p-4 bg-gray-800 rounded shadow">
            <div class="flex items-center gap-2">
              <label for="players" class="font-medium text-white">Number of Players:</label>
              <select id="players" v-model="playerCount" class="border rounded px-2 py-1 bg-gray-700 text-white">
                <option value="1">1 Player</option>
                <option value="2">2 Players</option>
              </select>
            </div>

            <div class="flex items-center text-white">
              <input type="checkbox" v-model="playAgainstAI" class="mr-2">
              <span>Play against AI</span>
            </div>

            <div class="flex flex-wrap gap-4 justify-center mt-4 w-full">
              <button @click="startGame" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Start Game
              </button>

              <Link :href="route('ai-game')" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition">
                Exit Game
              </Link>

              <button
                @click="joinGame"
                :disabled="userInGame"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Join Game
              </button>

              <button
                @click="leaveGame"
                :disabled="!userInGame"
                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Leave Game
              </button>
            </div>
          </div>

          <!-- Players In Game -->
          <div class="basis-[20rem] flex-grow p-4 bg-gray-800 rounded shadow overflow-y-auto">
            <h3 class="font-semibold text-lg mb-2 text-white">Players In Game</h3>
            <ul class="list-disc pl-5 text-white">
              <li v-for="user in currentGame?.users ?? []" :key="user.id">
                {{ user.name }}
              </li>
            </ul>
            <div v-if="(currentGame?.users?.length ?? 0) === 0" class="text-gray-400 mt-2">
              Waiting for players to join...
            </div>
          </div>

          <!-- Game Graph -->
          <div class="basis-[20rem] flex-grow p-4 bg-gray-800 rounded shadow">
            <GameGraphComponent ref="gameGraphRef" :gameId="gameId" />
          </div>

          <!-- Player Scores Table -->
          <div class="w-full">
            <div class="basis-[20rem] flex-grow p-4 bg-gray-800 rounded shadow">
              <h3 class="font-semibold text-lg mb-2 text-white">Player Scores</h3>
              <table class="w-full text-left border-collapse text-white">
                <thead>
                  <tr class="bg-gray-700">
                    <th class="p-2 border-b">Player</th>
                    <th class="p-2 border-b">Game Session</th>
                    <th class="p-2 border-b">Score</th>
                    <th class="p-2 border-b">Date Created</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="score in gameScores" :key="score.id">
                    <td class="p-2 border-b break-words">{{ score.user?.name }}</td>
                    <td class="p-2 border-b break-words">{{ score.session_id }}</td>
                    <td class="p-2 border-b break-words">{{ score.score }}</td>
                    <td class="p-2 border-b break-words">{{ formatDate(score.created_at) }}</td>
                  </tr>
                  <tr v-if="gameScores.length === 0">
                    <td colspan="4" class="p-2 text-center text-gray-400">No scores available</td>
                  </tr>
                </tbody>
              </table>
              
              <!-- Add Pagination for Scores -->
              <DynamicPagination
                :currentPage="scoresCurrentPage"
                :totalPages="scoresTotalPages"
                @change-page="changeScoresPage"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>