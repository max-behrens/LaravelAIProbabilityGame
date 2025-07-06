<script setup>
import { ref, defineProps, computed, onMounted } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import GameAuthenticatedLayout from '@/Layouts/GameAuthenticated.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import { useGames } from '@/Composables/useGames';
import { usePlayerInteractions } from '@/Composables/usePlayerInteractions'; // Import the new composable
import DynamicPagination from '@/Components/DynamicPagination.vue';
import GameGraphComponent from '@/Components/GameGraphComponent.vue';
import GameHeatmapComponent from '@/Components/GameHeatmapComponent.vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'pusher',
  key: 'c493e35de663a696d88e',
  cluster: 'mt1', // replace with your actual cluster
  forceTLS: true, // optional but recommended
});

// Props
const props = defineProps({
  gameId: { type: Number, required: true },
  game: Object,
  gameType: Object,
  gameQuestions: Array,
  auth: Object, 
});


// Use the player interactions composable
const {
  players,
  flashMessages,
  gameState,
  isInGame,
  fetchPlayers,
  broadcastJoin,
  broadcastLeave,
  changePlayerCount,
  answerQuestion,
  submitAnswers,
  addFlashMessage,
  removeFlashMessage,
  clearFlashMessages,
  registerCallbacks
} = usePlayerInteractions(props.gameId, props.auth);

// Debug logging
console.log('Game ID:', props.gameId);
console.log('Auth user:', props.auth?.user);
console.log('Pusher key being used: c493e35de663a696d88e');

// Reactive state
const currentGame = ref({ users: [] });
const gameScores = ref([]);
const errorMessage = ref('');
const playerCount = ref(1);
const playAgainstAI = ref(false);
const scoresCurrentPage = ref(1);
const scoresTotalPages = ref(1);
const submitting = ref(false);
const currentQuestionIndex = ref(0);
const answers = ref([]);
const isGameStarted = ref(false);
const gameGraphRef = ref(null);
const gameHeatmapRef = ref(null);

// Computed properties
const playersCount = computed(() => players.value.length);
const maxPlayers = computed(() => props.game?.max_players || 0);

const maxPlayersReached = computed(() => {
  return props.game.max_players && players.value.length >= props.game.max_players;
});

const isLastQuestion = computed(() => {
  return currentQuestionIndex.value === props.gameQuestions.length - 1;
});

// Watch for game state changes
const isWaitingForOthers = computed(() => gameState.value.waitingForOthers);
const isGameInProgress = computed(() => gameState.value.gameInProgress);

// Format date helper
const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleString();
};

// Fetch current game details
const fetchCurrentGame = async () => {
  try {
    const response = await axios.get(`/api/games/${props.gameId}`);
    currentGame.value = response.data;
  } catch (error) {
    errorMessage.value = 'Failed to load game details.';
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

// Updated game control functions
const startGame = async () => {
  try {
    const response = await axios.post(`/games/${props.gameId}/player-ready`, {
      userId: props.auth.user.id,
      userName: props.auth.user.name,
      requiredCount: playerCount.value,
    });

    if (response.data.status === 'waiting') {
      addFlashMessage('Waiting for other players to be ready...', 'success');
    } else if (response.data.status === 'started') {
      isGameStarted.value = true;
      addFlashMessage('Game started!', 'success');
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to signal readiness.';
    console.error(error);
  }
};

// Join the game with real-time updates
const joinGame = async () => {
  try {
    submitting.value = true;
    await axios.post(`/games/${props.gameId}/join`);
    await fetchPlayers(); // This will be updated via Pusher anyway
    addFlashMessage('You joined the game!', 'success');
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to join the game.';
    console.error(error);
  } finally {
    submitting.value = false;
  }
};

// Leave the game with real-time updates
const leaveGame = async () => {
  try {
    submitting.value = true;
    await axios.post(`/games/${props.gameId}/leave`);
    await fetchPlayers(); // This will be updated via Pusher anyway
    addFlashMessage('You left the game!', 'success');
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to leave the game.';
    console.error(error);
  } finally {
    submitting.value = false;
  }
};

// Handle player count changes
const onPlayerCountChange = async (newCount) => {
  playerCount.value = newCount;
  if (isInGame.value) {
    await changePlayerCount(newCount);
  }
};

// Navigation / submission for answers
const nextOrSubmit = async () => {
  if (!isLastQuestion.value) {
    // Answer the current question and broadcast
    await answerQuestion(currentQuestionIndex.value, answers.value[currentQuestionIndex.value]);
    currentQuestionIndex.value++;
  } else {
    submitting.value = true;

    try {
      const result = await submitAnswers(answers.value, playerCount.value);
      
      if (result.submitted) {
        addFlashMessage('Answers submitted successfully!', 'success');
        
        // // Refresh all data
        // await Promise.all([
        //   fetchGameScores(1),
        //   fetchCurrentGame()
        // ]);
        
        // // Refresh charts
        // if (gameGraphRef.value?.refreshChart) {
        //   await gameGraphRef.value.refreshChart();
        // }
        // if (gameHeatmapRef.value?.refreshHeatmap) {
        //   await gameHeatmapRef.value.refreshHeatmap();
        // }
        
        // Reset game state
        currentQuestionIndex.value = 0;
        answers.value = [];
        isGameStarted.value = false;
        
      } else if (result.waitingForOthers) {
          addFlashMessage('Answers stored, now waiting for other players to submit...', 'success');
      }
      
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
  fetchGameScores();

    // REGISTER CALLBACKS FOR LIVE UPDATES
    registerCallbacks({
      onScoresUpdate: async () => {
        console.log('🔄 Refreshing scores table...');
        await fetchGameScores(1); // Reset to first page and refresh
      },
      onGameUpdate: async () => {
        console.log('🔄 Refreshing game details...');
        await fetchCurrentGame();
      },
      onChartsUpdate: async () => {
        console.log('🔄 Refreshing charts...');
        // Refresh charts
        if (gameGraphRef.value?.refreshChart) {
          await gameGraphRef.value.refreshChart();
        }
        if (gameHeatmapRef.value?.refreshHeatmap) {
          await gameHeatmapRef.value.refreshHeatmap();
        }
      }
    });

    echo.channel(`game.${props.gameId}`)
      .listen('.player.ready', (data) => {
        console.log('Player ready:', data.userName);
        fetchPlayers();
        if (data.requiredCount && players.value.length >= data.requiredCount) {
          isGameStarted.value = true;
          addFlashMessage('Game started!', 'success');
        }
      });
});
</script>

<template>
  <Head title="AI Game Room" />

  <BreezeAuthenticatedLayout>
    <GameAuthenticatedLayout :currentGameId="props.gameId">

      <template #header>
        <div class="flex items-center justify-between">
          <!-- Title -->
          <h2 class="font-semibold text-md text-white leading-tight">
            Lobby {{ props.gameId }}: {{ props.gameType.name }}
          </h2>

          <!-- Back Button -->
          <Link
            :href="route('ai-game')"
            class="inline-block bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium py-1 px-2 rounded"
          >
            ← Back to AI Game Lobby
          </Link>
        </div>
      </template>

      <div class="py-4 mb-6">
        <div class="main-width mx-auto sm:px-6 lg:px-8">
          
          <!-- Flash Messages from Player Interactions -->
          <div v-for="flash in flashMessages" :key="flash.id" class="mb-2">
            <div 
              :class="{
                'bg-red-900 text-red-200 border-red-700': flash.type === 'error',
                'bg-green-900 text-green-200 border-green-700': flash.type === 'success',
                'bg-blue-900 text-blue-200 border-blue-700': flash.type === 'info',
                'bg-yellow-900 text-yellow-200 border-yellow-700': flash.type === 'warning'
              }"
              class="p-3 rounded border relative"
            >
              {{ flash.message }}
              <button 
                @click="removeFlashMessage(flash.id)"
                class="absolute top-1 right-2 text-xl font-bold opacity-70 hover:opacity-100"
              >
                ×
              </button>
            </div>
          </div>

          <!-- Original Flash Messages -->
          <div v-if="errorMessage" class="mb-4 p-4 bg-red-900 text-red-200 rounded border border-red-700">{{ errorMessage }}</div>


          <div class="flex flex-wrap gap-6 justify-center items-start">
            <!-- Question Input -->
            <div v-if="isGameStarted && !isWaitingForOthers" class="basis-full mb-6">
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
                  :disabled="submitting || isWaitingForOthers"
                  @click="nextOrSubmit"
                  class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
                >
                  {{ isLastQuestion ? (submitting ? 'Submitting...' : 'Submit') : 'Next' }}
                </button>
              </div>
            </div>

            <!-- Waiting Message -->
            <div v-if="isWaitingForOthers" class="basis-full mb-6 text-center">
              <div class="p-4 bg-yellow-900 text-yellow-200 rounded border border-yellow-700">
                <p class="text-lg font-semibold">Waiting for other players...</p>
                <p class="text-sm mt-2">Please wait while other players complete their actions.</p>
              </div>
            </div>

            <!-- Game Controls -->
            <div class="basis-full flex flex-wrap gap-4 justify-center p-4 bg-gray-800 rounded shadow">
              <div class="flex items-center gap-2 text-white">
                <label for="players">Number of Players:</label>
                <select 
                  id="players" 
                  :value="playerCount" 
                  @change="onPlayerCountChange($event.target.value)"
                  :disabled="isGameInProgress || isWaitingForOthers"
                  class="border rounded px-2 py-1 bg-gray-700 text-white disabled:opacity-50"
                >
                  <option value="1">1 Player</option>
                  <option value="2">2 Players</option>
                </select>
              </div>

              <div class="flex items-center text-white">
                <input 
                  type="checkbox" 
                  v-model="playAgainstAI" 
                  :disabled="isGameInProgress || isWaitingForOthers"
                  class="mr-2" 
                />
                <span>Play against AI</span>
              </div>

              <div class="flex flex-wrap gap-4 justify-center mt-4 w-full">
                <button 
                  @click="startGame" 
                  :disabled="!isInGame || isGameInProgress || isWaitingForOthers"
                  class="bg-green-900 hover:bg-green-800 text-green-200 font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ isWaitingForOthers ? 'Waiting...' : 'Start Game' }}
                </button>
                <button
                  @click="joinGame"
                  :disabled="isInGame || submitting || maxPlayersReached || isGameInProgress"
                  class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                >
                  Join Game
                </button>
                <button
                  @click="leaveGame"
                  :disabled="!isInGame || submitting || isGameInProgress"
                  class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:opacity-50"
                >
                  Leave Game
                </button>
              </div>
            </div>

            <!-- Players and Scores Row -->
            <div class="flex flex-wrap gap-6 w-full">
              <!-- Players -->
              <div class="min-w-[300px] basis-1/4 p-4 bg-gray-800 rounded shadow">
                <h3 class="font-semibold text-lg mb-2">Players In Game</h3>

                <div class="mb-2 text-gray-300 font-semibold">
                  Players: {{ playersCount }} / {{ maxPlayers }}
                </div>

                <div v-if="maxPlayersReached" class="mb-2 p-2 bg-red-700 bg-red-800 text-red-100 rounded text-center font-bold">
                  Max Players Reached
                </div>
                
                <!-- Use players from the composable instead of currentGame.users -->
                <ul class="list-disc pl-5">
                  <li v-for="user in players" :key="user.id">{{ user.name }}</li>
                </ul>
                
                <div v-if="players.length === 0" class="text-gray-400 mt-2">
                  Waiting for players to join...
                </div>
              </div>

              <!-- Player Scores -->
              <div class="flex-1 min-w-[300px] p-4 bg-gray-800 rounded shadow">
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
                      <td class="p-2 border-b text-white">{{ score.user?.name }}</td>
                      <td class="p-2 border-b text-white">{{ score.session_id }}</td>
                      <td class="p-2 border-b text-white">{{ score.score }}</td>
                      <td class="p-2 border-b text-white">{{ formatDate(score.created_at) }}</td>
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
            <div class="flex flex-col lg:flex-row gap-6 w-full">
              <!-- Score Heatmap -->
              <div class="w-full lg:w-1/2 lg:max-w-[50%] overflow-hidden">
                <GameHeatmapComponent 
                  ref="gameHeatmapRef" 
                  :gameId="gameId" 
                  :gameQuestions="gameQuestions" 
                />
              </div>

              <!-- Score Trends -->
              <div class="w-full lg:w-1/2 lg:max-w-[50%] overflow-hidden">
                <GameGraphComponent 
                  ref="gameGraphRef" 
                  :gameId="gameId" 
                />
              </div>
            </div>


          </div>
          </div>
        </div>
    </GameAuthenticatedLayout>
  </BreezeAuthenticatedLayout>

</template>