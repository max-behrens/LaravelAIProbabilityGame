<script setup>
import { ref, defineProps, computed, onMounted } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import { useGames } from '@/Composables/useGames';
import DynamicPagination from '@/Components/DynamicPagination.vue';
import GameGraphComponent from '@/Components/GameGraphComponent.vue';
import GameHeatmapComponent from '@/Components/GameHeatmapComponent.vue';
import axios from 'axios';

// Props
const props = defineProps({
  gameId: { type: Number, required: true },
  game: Object,
  gameType: Object,
  gameQuestions: Array,
  auth: Object, 
});

// Reactive state
const currentGame = ref({ users: [] });
const gameScores = ref([]);
const errorMessage = ref('');
const successMessage = ref('');
const playerCount = ref(1);
const playAgainstAI = ref(false);
const userInGame = ref(false);
const scoresCurrentPage = ref(1);
const scoresTotalPages = ref(1);
const submitting = ref(false);
const currentQuestionIndex = ref(0);
const answers = ref([]);
const isGameStarted = ref(false);
const gameGraphRef = ref(null);
const gameHeatmapRef = ref(null);
const playersCount = computed(() => currentGame.value.users.length);
const maxPlayers = computed(() => props.game?.max_players || 0);

const getCurrentUserId = () => props.auth?.user?.id ?? null;

const maxPlayersReached = computed(() => {
  console.log('props.game:', props.game);
  console.log('maxPlayers:', props.game.max_players);
  console.log('currentGame users count:', currentGame.value.users.length);

  return props.game.max_players && currentGame.value.users.length >= props.game.max_players;
});

// Computed: detect if current question is the last one
const isLastQuestion = computed(() => {
  return currentQuestionIndex.value === props.gameQuestions.length - 1;
});

// Format date helper
const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleString();
};

// Fetch current game details including users
const fetchCurrentGame = async () => {
  try {
    const response = await axios.get(`/api/games/${props.gameId}`);
    currentGame.value = response.data;
  } catch (error) {
    errorMessage.value = 'Failed to load game details.';
    console.error(error);
  }
};

// Fetch the players currently in the game (updated from backend)
const fetchPlayers = async () => {
  try {
    const response = await axios.get(`/api/games/${props.gameId}/players`);
    currentGame.value.users = response.data;

    const currentUserId = getCurrentUserId();
    if (currentUserId) {
      userInGame.value = response.data.some(player => player.id === currentUserId);
    } else {
      userInGame.value = false;
    }
  } catch (error) {
    errorMessage.value = 'Failed to load players.';
    console.error(error);
  }
};

// Fetch paginated game scores
const fetchGameScores = async (page = 1) => {
  try {
    const response = await axios.get(`/api/games/${props.gameId}/scores?page=${page}`);
    gameScores.value = response.data.data;
    scoresTotalPages.value = response.data.last_page;
    scoresCurrentPage.value = response.data.current_page;
  } catch (error) {
    errorMessage.value = 'Failed to load player scores.';
    console.error(error);
  }
};

// Pagination handler
const changeScoresPage = (page) => {
  if (page < 1 || page > scoresTotalPages.value) return;
  fetchGameScores(page);
};

// Game control stubs
const startGame = () => {
  isGameStarted.value = true;
  successMessage.value = 'Game started!';
};

// Join the game — call backend and refresh player list
const joinGame = async () => {
  try {
    submitting.value = true;

    // Optimistically mark user as in game immediately
    userInGame.value = true;

    await axios.post(`/games/${props.gameId}/join`);
    successMessage.value = 'You joined the game!';

    // Refresh player list to sync backend state
    await fetchPlayers();

  } catch (error) {
    // Revert if error occurs
    userInGame.value = false;
    errorMessage.value = error.response?.data?.message || 'Failed to join the game.';
    console.error(error);
  } finally {
    submitting.value = false;
  }
};

// Leave the game — call backend and refresh player list
const leaveGame = async () => {
  try {
    submitting.value = true;

    // Optimistically mark user as not in game immediately
    userInGame.value = false;

    await axios.post(`/games/${props.gameId}/leave`);
    successMessage.value = 'You left the game.';

    // Refresh player list to sync backend state
    await fetchPlayers();

  } catch (error) {
    // Revert if error occurs
    userInGame.value = true;
    errorMessage.value = error.response?.data?.message || 'Failed to leave the game.';
    console.error(error);
  } finally {
    submitting.value = false;
  }
};

// Navigation / submission for answers
const nextOrSubmit = async () => {
  axios.defaults.withCredentials = true;
  if (!isLastQuestion.value) {
    currentQuestionIndex.value++;
  } else {
    submitting.value = true;

    try {
      // Use the API route for consistency
      await axios.post(`/games/${props.gameId}/submit-answer`, {
          answers: answers.value,
      });
      
      successMessage.value = 'Answers submitted successfully!';
      
      // Refresh all data to show the new submission immediately
      await Promise.all([
        fetchGameScores(1), // Reset to first page to see latest scores
      ]);
      
      // Refresh the GameGraphComponent
      if (gameGraphRef.value && typeof gameGraphRef.value.refreshChart === 'function') {
        await gameGraphRef.value.refreshChart();
      }
      
      // Refresh the GameHeatmapComponent
      if (gameHeatmapRef.value && typeof gameHeatmapRef.value.refreshHeatmap === 'function') {
        await gameHeatmapRef.value.refreshHeatmap();
      }
      
      // Refresh game details if needed
      await fetchCurrentGame();
      
      // Reset game state for next round
      currentQuestionIndex.value = 0;
      answers.value = [];
      isGameStarted.value = false;
      
    } catch (error) {
      errorMessage.value = error.response?.data?.message || 'Failed to submit answers.';
      console.error('Submission error:', error);
    } finally {
      submitting.value = false;
    }
  }
};

// Lifecycle: fetch data
onMounted(() => {
  fetchCurrentGame();
  fetchPlayers();
  fetchGameScores();
});
</script>

<template>
  <Head title="AI Game Room" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-md text-white leading-tight">
        Lobby {{ props.gameId }}: {{ props.gameType.name }}
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <!-- Flash Messages -->
      <div v-if="errorMessage" class="mb-4 p-4 bg-red-900 text-red-200 rounded border border-red-700">{{ errorMessage }}</div>
      <div v-if="successMessage" class="mb-4 p-4 bg-green-900 text-green-200 rounded border border-green-700">{{ successMessage }}</div>

        <div class="flex flex-wrap gap-6 justify-center items-start">
          <!-- Question Input -->
          <div v-if="isGameStarted" class="basis-full mb-6">
            <div class="text-center mb-2 text-gray-400 text-sm font-medium">
              Question {{ currentQuestionIndex + 1 }} / {{ props.gameQuestions.length }}
            </div>
            <div class="text-center mb-4 text-gray-200 text-xl font-semibold">
              {{ props.gameQuestions[currentQuestionIndex]?.question }}
            </div>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
              <input
                v-model="answers[currentQuestionIndex]"
                class="px-4 py-2 rounded w-full sm:w-2/3 text-gray-200 placeholder-gray-400 !text-gray-200"
                placeholder="Your answer"
              />

            <button
              :disabled="submitting"
              @click="nextOrSubmit"
              class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
            >
              {{ isLastQuestion ? (submitting ? 'Submitting...' : 'Submit') : 'Next' }}
            </button>

            </div>
          </div>

          <!-- Game Controls -->
          <div class="basis-full flex flex-wrap gap-4 justify-center p-4 bg-gray-800 rounded shadow">
            <div class="flex items-center gap-2 text-white">
              <label for="players">Number of Players:</label>
              <select id="players" v-model="playerCount" class="border rounded px-2 py-1 bg-gray-700 text-white">
                <option value="1">1 Player</option>
                <option value="2">2 Players</option>
              </select>
            </div>

            <div class="flex items-center text-white">
              <input type="checkbox" v-model="playAgainstAI" class="mr-2" />
              <span>Play against AI</span>
            </div>

            <div class="flex flex-wrap gap-4 justify-center mt-4 w-full">
              <button 
                @click="startGame" 
                :disabled="!userInGame"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Start Game
              </button>
              <Link :href="route('ai-game')" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                Exit Game
              </Link>
              <button
                @click="joinGame"
                :disabled="userInGame || submitting || maxPlayersReached"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
              >
                Join Game
              </button>
              <button
                @click="leaveGame"
                :disabled="!userInGame  || submitting"
                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:opacity-50"
              >
                Leave Game
              </button>
            </div>
          </div>

          <!-- Players and Scores Row -->
          <div class="flex flex-wrap gap-6 w-full">
            <!-- Players -->
            <div class="min-w-[300px] basis-1/4 p-4 bg-gray-800 rounded shadow text-gray-200">
              <h3 class="font-semibold text-lg mb-2">Players In Game</h3>

              <div class="mb-2 text-gray-300 font-semibold">
                Players: {{ playersCount }} / {{ maxPlayers }}
              </div>

              <div v-if="maxPlayersReached" class="mb-2 p-2 bg-red-700 bg-red-800 text-red-100 rounded text-center font-bold">
                Max Players Reached
              </div>
              
              <ul class="list-disc pl-5">
                <li v-for="user in currentGame?.users ?? []" :key="user.id">{{ user.name }}</li>
              </ul>
              
              <div v-if="(currentGame?.users?.length ?? 0) === 0" class="text-gray-400 mt-2">
                Waiting for players to join...
              </div>
            </div>

            <!-- Player Scores -->
            <div class="flex-1 min-w-[300px] p-4 bg-gray-800 rounded shadow text-gray-200">
              <h3 class="font-semibold text-lg mb-2">Player Scores</h3>
              <table class="w-full text-left border-collapse">
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
                    <td class="p-2 border-b">{{ score.user?.name }}</td>
                    <td class="p-2 border-b">{{ score.session_id }}</td>
                    <td class="p-2 border-b">{{ score.score }}</td>
                    <td class="p-2 border-b">{{ formatDate(score.created_at) }}</td>
                  </tr>
                  <tr v-if="gameScores.length === 0">
                    <td colspan="4" class="p-2 text-center text-gray-400">No scores available</td>
                  </tr>
                </tbody>
              </table>
              <DynamicPagination
                :currentPage="scoresCurrentPage"
                :totalPages="scoresTotalPages"
                @change-page="changeScoresPage"
              />
            </div>
          </div>

          <!-- Charts Row -->
          <div class="flex flex-col lg:flex-row gap-6 w-full mt-6">
            <!-- Score Heatmap -->
            <GameHeatmapComponent 
              ref="gameHeatmapRef" 
              :gameId="gameId" 
              :gameQuestions="gameQuestions" 
            />

            <!-- Score Trends -->
            <div class="basis-1/2">
              <GameGraphComponent 
                ref="gameGraphRef" 
                :gameId="gameId" 
              />
            </div>
          </div>

        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>