<script setup>
import { ref, defineProps, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import GameAuthenticatedLayout from '@/Layouts/GameAuthenticated.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import { useAI, createAI } from '@/Composables/useAI';
import { usePlayerInteractions } from '@/Composables/usePlayerInteractions';
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
    cluster: 'mt1',
    forceTLS: true,
});

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
const aiScores = ref([]);
const errorMessage = ref('');
const playerCount = ref(1);
const scoresCurrentPage = ref(1);
const scoresTotalPages = ref(1);
const aiScoresCurrentPage = ref(1);
const aiScoresTotalPages = ref(1);
const submitting = ref(false);
const currentQuestionIndex = ref(0);
const answers = ref([]);
const isGameStarted = ref(false);
const gameIsOver = ref(false); // Flag to indicate game is finished, but not necessarily reset yet
const gameGraphRef = ref(null);
const gameHeatmapRef = ref(null);
const showVerticalNav = ref(false);
const isNavigatingProgrammatically = ref(false);
const currentNavSection = ref(0);
const excludeAI = ref(true);

// Initialize player interactions first
const {
    players,
    flashMessages,
    gameState,
    isInGame,
    preSubmittedAnswers,
    fetchPlayers,
    changePlayerCount,
    answerQuestion,
    submitAnswers,
    addFlashMessage,
    removeFlashMessage,
    clearFlashMessages,
    registerCallbacks,
    setAIModule // Get the setAIModule function
} = usePlayerInteractions(props.gameId, props.auth);

// Create AI with dependency getter function
const aiModule = createAI(props.gameId, () => ({
    gameState: gameState.value,
    players: players.value,
    currentQuestionIndex: currentQuestionIndex.value
}));

const {
    aiAnswers,
    aiLoading,
    aiError,
    playWithAI,
    allPlayersAnswered,
    getAIAnswer,
    getAIAnswerForQuestion,
    hasAIAnswered,
    resetAI
} = aiModule;

// Set AI module reference in player interactions
setAIModule(aiModule);


// Debug logging
console.log('Game ID:', props.gameId);
console.log('Auth user:', props.auth?.user);
console.log('Pusher key being used: c493e35de663a696d88e');

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

// New computed property for controlling question input visibility
const showQuestionInput = computed(() => {
    return isGameStarted.value && !isWaitingForOthers.value && !gameIsOver.value;
});

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

const fetchGameScores = async (page = 1) => {
    try {
        const params = new URLSearchParams({
            page: page.toString()
        });
        
        // Add filter parameters
        if (appliedFilters.value.dateRange[0] && appliedFilters.value.dateRange[1]) {
            params.set('start_date', appliedFilters.value.dateRange[0].toISOString().split('T')[0]);
            params.set('end_date', appliedFilters.value.dateRange[1].toISOString().split('T')[0]);
        }
        
        if (appliedFilters.value.userIds.length > 0) {
            params.set('user_ids', appliedFilters.value.userIds.join(','));
            if (appliedFilters.value.andUsers) {
                params.set('and_users', 'true');
            }
        }
        
        const response = await axios.get(`/api/games/${props.gameId}/scores?${params.toString()}`);
        gameScores.value = response.data.data;
        scoresTotalPages.value = response.data.last_page;
        scoresCurrentPage.value = response.data.current_page;
    } catch (error) {
        errorMessage.value = 'Failed to load player scores.';
        console.error(error);
    }
};

const fetchAIScores = async (page = 1) => {
    try {
        // If AI is excluded, set empty array and return early
        if (appliedFilters.value.excludeAI) {
            aiScores.value = [];
            aiScoresTotalPages.value = 1;
            aiScoresCurrentPage.value = 1;
            return;
        }

        const params = new URLSearchParams({
            page: page.toString()
        });
        
        // Add date filter parameters (AI scores don't need user filters)
        if (appliedFilters.value.dateRange[0] && appliedFilters.value.dateRange[1]) {
            params.set('start_date', appliedFilters.value.dateRange[0].toISOString().split('T')[0]);
            params.set('end_date', appliedFilters.value.dateRange[1].toISOString().split('T')[0]);
        }
        
        // Add exclude AI parameter
        params.set('exclude_ai', appliedFilters.value.excludeAI.toString());
        
        const response = await axios.get(`/api/games/${props.gameId}/ai-scores?${params.toString()}`);
        aiScores.value = response.data.data || []; // Ensure it's always an array
        aiScoresTotalPages.value = response.data.last_page || 1;
        aiScoresCurrentPage.value = response.data.current_page || 1;
    } catch (error) {
        errorMessage.value = 'Failed to load AI scores.';
        console.error(error);
        // Set defaults on error
        aiScores.value = [];
        aiScoresTotalPages.value = 1;
        aiScoresCurrentPage.value = 1;
    }
};


// Pagination handler
const changeScoresPage = (page) => {
    if (page < 1 || page > scoresTotalPages.value) return;
    fetchGameScores(page);
};

const changeAIScoresPage = (page) => {
    if (page < 1 || page > aiScoresTotalPages.value) return;
    fetchAIScores(page);
};

const showSinglePlayerAIWarning = computed(() => {
    return playerCount.value === 1 && !playWithAI.value;
});

// Updated game control functions
const startGame = async () => {
    try {
        // Reset session before starting new game
        await axios.post(`/games/${props.gameId}/reset-session`);
        
        const response = await axios.post(`/games/${props.gameId}/player-ready`, {
            userId: props.auth.user.id,
            userName: props.auth.user.name,
            requiredCount: playerCount.value,
        });

        console.log('playerCount.value: ' + playerCount.value);

        if (response.data.status === 'waiting') {
            addFlashMessage('Waiting for other players to be ready...', 'success');
        } else if (response.data.status === 'started') {
                if (showSinglePlayerAIWarning.value) {
                    // The message will be displayed in the template, so no flash message is needed here.
                    // You might still want to add an info message or a different flash message.
                    addFlashMessage('Cannot start game. Please enable "Play with AI" or add more players.', 'warning');
                    return; // Stop the function from proceeding
                } else {
                    isGameStarted.value = true;
                    gameIsOver.value = false; // Ensure gameIsOver is false when game starts
                    addFlashMessage('Game started!', 'success');
            }
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
        await fetchPlayers();
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
        await fetchPlayers();
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

const nextOrSubmit = async () => {
    if (!isLastQuestion.value) {
        // ENHANCED: Use the new answerQuestion function with auto-progression
        const result = await answerQuestion(currentQuestionIndex.value, answers.value[currentQuestionIndex.value], playerCount.value);

        if (result.submitted) {
            // Answer was submitted successfully (single player or all players answered)
            // Check if we're playing with AI and need to wait for AI answer
            if (playWithAI.value && props.gameQuestions[currentQuestionIndex.value]) {
                // For single player: Get AI answer immediately
                if (playerCount.value === 1) {
                    console.log('Single player: Requesting AI answer for question:', currentQuestionIndex.value);
                    await getAIAnswerForQuestion(
                        props.gameQuestions[currentQuestionIndex.value].question,
                        props.gameId,
                        currentQuestionIndex.value
                    );
                    // Progress to next question immediately after AI responds
                    currentQuestionIndex.value++;
                } else {
                    // For multiplayer: Only the first player to trigger "all answered" should request AI
                    console.log('Multiplayer: All players answered, checking if need to request AI');
                    
                    // If AI hasn't answered this question yet, request it
                    if (!hasAIAnswered(currentQuestionIndex.value)) {
                        console.log('Requesting AI answer for question:', currentQuestionIndex.value);
                        await getAIAnswerForQuestion(
                            props.gameQuestions[currentQuestionIndex.value].question,
                            props.gameId,
                            currentQuestionIndex.value
                        );
                        
                        // Broadcast that AI has answered and all players can progress
                        await axios.post(`/api/games/${props.gameId}/broadcast`, {
                            event: 'ai.answered',
                            data: {
                                questionIndex: currentQuestionIndex.value,
                                aiAnswer: aiAnswers.value[currentQuestionIndex.value]?.answer,
                                aiScore: aiAnswers.value[currentQuestionIndex.value]?.score, // Add this line
                                isCorrect: aiAnswers.value[currentQuestionIndex.value]?.isCorrect, // Add this too
                                timestamp: new Date().toISOString()
                            }
                        });
                    }
                    
                    // In multiplayer, progress will happen via Pusher event
                    // Don't increment currentQuestionIndex here - wait for the event
                }
            } else {
                // No AI - progress immediately in single player, or wait for multiplayer sync
                if (playerCount.value === 1) {
                    currentQuestionIndex.value++;
                } else {
                    // Multiplayer without AI - progress will happen via Pusher event
                    // Don't increment currentQuestionIndex here
                }
            }
        } else if (result.waitingForOthers && result.preAnswered) {
            // Answer was pre-submitted, waiting for auto-progression
            addFlashMessage('Your answer is ready! Waiting for other players...', 'success');
            // Don't increment currentQuestionIndex - this will happen automatically via Pusher
        }
    } else {
        // This is the LAST question - use existing final submission logic
        submitting.value = true;

        try {
            if (playWithAI.value) {
                if (!hasAIAnswered(currentQuestionIndex.value)) {
                    addFlashMessage('Waiting for AI to answer the final question...', 'info');
                    console.log('Triggering AI answer for final question:', currentQuestionIndex.value);
                    await getAIAnswerForQuestion(
                        props.gameQuestions[currentQuestionIndex.value].question,
                        props.gameId,
                        currentQuestionIndex.value
                    );
                }
            }

            const result = await submitAnswers(answers.value, playerCount.value);

            if (result.submitted) {
                addFlashMessage('Answers submitted successfully! Game Completed.', 'success');
                gameIsOver.value = true;
                isGameStarted.value = false;

                setTimeout(() => {
                    resetGameState();
                    console.log('Game state fully reset on submitter - ready for new game');
                }, 2000);

            } else if (result.waitingForOthers && result.preSubmitted) {
                addFlashMessage('Your answers are ready! Waiting for other players to submit...', 'success');
                gameState.value.waitingForOthers = true;
                isGameStarted.value = false;
                preSubmittedAnswers.value = answers.value;
                console.log('✅ Answers pre-submitted, waiting for auto-submission...');
            }

        } catch (error) {
            errorMessage.value = error.response?.data?.message || 'Failed to submit answers.';
            console.error('Submission error:', error);
        } finally {
            submitting.value = false;
        }
    }
};

const debugAIAnswers = computed(() => {
    const debug = {};
    Object.keys(aiAnswers.value).forEach(key => {
        debug[key] = {
            hasAnswer: !!aiAnswers.value[key]?.answer,
            hasScore: aiAnswers.value[key]?.score !== undefined,
            score: aiAnswers.value[key]?.score
        };
    });
    return debug;
});

// Function to handle starting a new game (called by a "Play Again" button)
const startNewGame = () => {
    console.log('Starting a new game - resetting state...');
    resetGameState();
    clearFlashMessages();
    addFlashMessage('Ready to start a new game!', 'success');
};

const resetGameState = () => {
    currentQuestionIndex.value = 0;
    answers.value = [];
    isGameStarted.value = false;
    gameIsOver.value = false;
    gameState.value.waitingForOthers = false; // Ensure waiting state is also reset
    resetAI();
    console.log('Game state fully reset - ready for new game');
};

// Vertical Nav Section:


const navSections = [
    { id: 'main', name: 'Main' },
    { id: 'scores', name: 'Scores' },
    { id: 'stats', name: 'Stats' },
];

const navigateSection = (direction) => {
    isNavigatingProgrammatically.value = true; 

    const newIndex = direction === 'up'
        ? Math.max(0, currentNavSection.value - 1)
        : Math.min(navSections.length - 1, currentNavSection.value + 1);

    // Update currentNavSection immediately for instant feedback
    currentNavSection.value = newIndex; 
    
    scrollToSection(newIndex);
};

const scrollToSection = (sectionIndex) => {
    const section = navSections[sectionIndex];
    const targetElement = document.getElementById(section.id);

    if (targetElement) {
        const yOffset = -80;
        const y = targetElement.getBoundingClientRect().top + window.pageYOffset + yOffset;
        
        window.scrollTo({ 
            top: y, 
            behavior: 'smooth' 
        });

        // Use requestAnimationFrame for more precise scroll end detection
        // or a slightly longer timeout if that's too complex.
        // For now, let's keep a robust timeout for simplicity.
        setTimeout(() => {
            isNavigatingProgrammatically.value = false;
            // Force an update to showVerticalNav after scroll is likely finished
            // This ensures it correctly hides if at the top.
            updateNavigation(); 
        }, 800); // Adjust timeout based on your scroll behavior duration
    } else if (section.id === 'main') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
            isNavigatingProgrammatically.value = false;
            updateNavigation(); // Ensure visibility is updated for 'main' section
        }, 800);
    }
};

const updateNavigation = () => {
    const scrollY = window.scrollY;
    const windowHeight = window.innerHeight;

    // This part should *always* update, regardless of programmatic navigation
    showVerticalNav.value = scrollY > windowHeight * 0.3;

    // Only update currentNavSection if not navigating programmatically
    if (isNavigatingProgrammatically.value) {
        return; // Exit here to prevent flickering of currentNavSection name
    }

    const sectionElements = navSections.map((section, index) => ({
        element: document.getElementById(section.id),
        index: index
    })).filter(item => item.element);

    const scrollPosition = scrollY + windowHeight * 0.4;

    for (let i = sectionElements.length - 1; i >= 0; i--) {
        const { element, index } = sectionElements[i];
        if (element.offsetTop <= scrollPosition) {
            currentNavSection.value = index;
            break;
        }
    }
};


const appliedFilters = ref({
    dateRange: [null, null],
    userIds: [],
    andUsers: false,
    excludeAI: true
});

// Add this function to parse URL parameters for filters
const parseFiltersFromUrl = () => {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Parse date range
    const startDate = urlParams.get('start_date');
    const endDate = urlParams.get('end_date');
    if (startDate && endDate) {
        appliedFilters.value.dateRange = [new Date(startDate), new Date(endDate)];
    }
    
    // Parse user IDs
    const userIds = urlParams.get('user_ids');
    if (userIds) {
        appliedFilters.value.userIds = userIds.split(',').map(id => parseInt(id)).filter(id => !isNaN(id));
    }
    
    // Parse AND users
    appliedFilters.value.andUsers = urlParams.get('and_users') === 'true';
    
    // Parse exclude AI - defaults to true if not present
    appliedFilters.value.excludeAI = urlParams.get('exclude_ai') !== 'false';
};

const handleFilterChange = (event) => {
    appliedFilters.value = {
        dateRange: event.detail.dateRange || [null, null],
        userIds: event.detail.userIds || [],
        andUsers: event.detail.andUsers || false,
        excludeAI: event.detail.excludeAI !== undefined ? event.detail.excludeAI : true
    };
    
    // Refresh scores when filters change
    fetchGameScores(1);
    fetchAIScores(1);
};

watch(() => gameState.value.gameInProgress, (newVal, oldVal) => {
    if (newVal && !oldVal) {
        console.log('Game started - AI answers preserved:', Object.keys(aiAnswers.value));
    }
});


// Lifecycle: fetch data
onMounted(() => {
    fetchCurrentGame();
    fetchGameScores();
    fetchAIScores();

    // Vertical Nav:
    window.addEventListener('scroll', updateNavigation);

        // Parse initial filters from URL
    parseFiltersFromUrl();
    
    // Listen for filter changes from GameAuthenticated layout
    window.addEventListener('gameFiltersChanged', handleFilterChange);
    
    // Fetch users on mount (assuming fetchUsers is defined elsewhere in your script)
    // await fetchUsers(); 

    nextTick(() => {
        updateNavigation();
    });

    // REGISTER CALLBACKS FOR LIVE UPDATES INCLUDING UI RESET
    registerCallbacks({
        onScoresUpdate: async () => {
            console.log('🔄 Refreshing scores table...');
            await fetchGameScores(1);
            await fetchAIScores();
        },
        onGameUpdate: async () => {
            console.log('🔄 Refreshing game details...');
            await fetchCurrentGame();
        },
        onChartsUpdate: async () => {
            console.log('🔄 Refreshing charts...');
            if (gameGraphRef.value?.refreshChart) {
                await gameGraphRef.value.refreshChart();
            }
            if (gameHeatmapRef.value?.refreshHeatmap) {
                await gameHeatmapRef.value.refreshHeatmap();
            }
        },
        // UI reset callback for auto-submitted users
        onGameComplete: async (data) => {
            console.log('🔄 Received game complete event. Resetting UI state...');
            gameIsOver.value = true;
            isGameStarted.value = false;
            gameState.value.waitingForOthers = false;

            setTimeout(() => {
                resetGameState();
                console.log('Auto-submitted user UI state fully reset - ready for new game');
            }, 2000);
        },

        // ENHANCED: Handle both AI answered and question progression
        onQuestionProgress: async (questionIndex, allAnswers = null) => {
            console.log('🔄 Received question progress event for question:', questionIndex);
            
            // If this is from an AI answer event, just progress
            if (allAnswers === null) {
                if (questionIndex === currentQuestionIndex.value) {
                    currentQuestionIndex.value++;
                    addFlashMessage('Moving to next question!', 'success');
                }
            } else {
                // This is from auto-progression - all players answered
                if (questionIndex === currentQuestionIndex.value) {
                    // Check if we need AI to answer before progressing
                    if (playWithAI.value && !hasAIAnswered(questionIndex)) {
                        console.log('All players answered, waiting for AI...');
                        addFlashMessage('All players answered! Waiting for AI...', 'info');
                        // AI will be requested by the first player who triggered this
                        // We'll progress when we receive the ai.answered event
                    } else {
                        // No AI needed or AI already answered - progress immediately
                        currentQuestionIndex.value++;
                        addFlashMessage('All players answered! Moving to next question!', 'success');
                    }
                }
            }
        }
    });

    echo.channel(`game.${props.gameId}`)
        .listen('.player.ready', (data) => {
            console.log('Player ready:', data.userName);
            fetchPlayers();
            if (data.status === 'started') { // Use the status from the server event
                isGameStarted.value = true;
                gameIsOver.value = false; // Ensure gameIsOver is false when game starts
                addFlashMessage('Game started!', 'success');
            }
        });
});

onUnmounted(() => {
    window.removeEventListener('scroll', updateNavigation);
    window.removeEventListener('gameFiltersChanged', handleFilterChange);
});
</script>

<template>
    <Head title="AI Game Room" />

    <BreezeAuthenticatedLayout>
        <GameAuthenticatedLayout :currentGameId="props.gameId">


                <!-- VERTICAL NAV -->

                <transition name="fade">
                    <div
                    v-if="showVerticalNav"
                    class="fixed left-4 top-1/2 transform -translate-y-1/2 z-10 bg-gray-800 backdrop-blur-sm rounded-lg p-2 shadow-lg hidden sm:block"
                    >
                    <div class="flex flex-col space-y-2">
                        <button 
                        @click="navigateSection('up')"
                        :disabled="currentNavSection === 0"
                        class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed group"
                        >
                        <svg class="w-5 h-5 text-white group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                        </svg>
                        </button>
                        
                        <div class="text-center py-2">
                        <div class="text-white text-xs font-medium whitespace-nowrap">
                            {{ navSections[currentNavSection]?.name || 'Main' }}
                        </div>
                        </div>
                        
                        <button 
                        @click="navigateSection('down')"
                        :disabled="currentNavSection === navSections.length - 1"
                        class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed group"
                        >
                        <svg class="w-5 h-5 text-white group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                        </button>
                    </div>
                    </div>
                </transition> 
                        

            <div class="py-4 mb-6">
                <div class="main-width mx-auto sm:px-6 lg:px-8">

                    <div v-if="errorMessage" class="mb-4 p-4 bg-red-900 text-red-200 rounded border border-red-700">
                        {{ errorMessage }}</div>


                    <div class="flex flex-wrap gap-6 justify-center items-start">
                        <div v-if="showQuestionInput" class="basis-full mb-6">
                            <div class="text-center mb-2 text-gray-400 text-sm font-medium">
                                Question {{ currentQuestionIndex + 1 }} / {{ props.gameQuestions.length }}
                            </div>
                            <div class="text-center mb-4 text-white text-xl font-semibold">
                                {{ props.gameQuestions[currentQuestionIndex]?.question }}
                            </div>
                            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                <input v-model="answers[currentQuestionIndex]"
                                    class="px-4 py-2 rounded w-full sm:w-2/3 text-gray-200 placeholder-gray-400 !text-gray-200"
                                    placeholder="Your answer" />

                                <button :disabled="submitting" @click="nextOrSubmit"
                                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50">
                                    {{ isLastQuestion ? (submitting ? 'Submitting...' : 'Submit') : 'Next' }}
                                </button>
                            </div>
                        </div>

                        <div v-for="flash in flashMessages" :key="flash.id" class="mb- w-full">
                            <div :class="{
                                    'bg-red-900 text-red-200 border-red-700': flash.type === 'error',
                                    'bg-green-900 text-green-200 border-green-700': flash.type === 'success',
                                    'bg-blue-900 text-blue-200 border-blue-700': flash.type === 'info',
                                    'bg-yellow-900 text-yellow-200 border-yellow-700': flash.type === 'warning'
                                }" class="p-3 rounded border relative">
                                {{ flash.message }}
                                <button @click="removeFlashMessage(flash.id)"
                                    class="absolute top-1 right-2 text-xl font-bold opacity-70 hover:opacity-100">
                                    ×
                                </button>
                            </div>
                        </div>

                        <div class="basis-full flex flex-wrap gap-4 justify-center p-4 bg-gray-800 rounded shadow">
                            <div class="flex items-center gap-2 text-white">
                                <label for="players">Number of Players:</label>
                                <select id="players" :value="playerCount" @change="onPlayerCountChange($event.target.value)"
                                    :disabled="isGameInProgress || isWaitingForOthers || gameIsOver"
                                    class="border rounded px-2 py-1 bg-gray-700 text-white disabled:opacity-50">
                                    <option value="1">1 Player</option>
                                    <option value="2">2 Players</option>
                                </select>
                            </div>

                            <div class="flex items-center text-white">
                                <input type="checkbox" v-model="playWithAI"
                                    :disabled="isGameInProgress || isWaitingForOthers || gameIsOver" class="mr-2" />
                                <span>Play with AI</span>
                            </div>

                            <div class="flex flex-wrap gap-4 justify-center mt-4 w-full">
                                <button @click="startGame"
                                    :disabled="!isInGame || (isGameInProgress && !gameIsOver) || isWaitingForOthers"
                                    class="bg-green-900 hover:bg-green-800 text-green-200 font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ isWaitingForOthers ? 'Waiting...' : 'Start Game' }}
                                </button>
                                
                                <button @click="joinGame"
                                    :disabled="isInGame || submitting || maxPlayersReached || (isGameInProgress && !gameIsOver)"
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
                                    Join Game
                                </button>
                                
                                <button @click="leaveGame"
                                    :disabled="!isInGame || submitting || (isGameInProgress && !gameIsOver)"
                                    class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:opacity-50">
                                    Leave Game
                                </button>
                            </div>
                        </div>
                        

                        <div v-if="isWaitingForOthers && !gameIsOver" class="basis-full mb-6 text-center">
                            <div class="p-4 bg-yellow-900 text-yellow-200 rounded border border-yellow-700">
                                <p class="text-lg font-semibold">Waiting for other players...</p>
                                <p class="text-sm mt-2">Please wait while other players complete their actions.</p>
                            </div>
                        </div>

                        <section id="scores">
                        </section>

                        <section id="tats">
                        </section>

                        <div v-if="playWithAI" class="flex flex-wrap gap-6 w-full">
                          <div class="bg-gray-800 min-w-[300px] basis-1/4 p-4 rounded border border-gray-700">
                              <h4 class="text-white font-semibold mb-2">AI Player Status</h4>

                              <div v-if="aiLoading" class="text-yellow-400 flex items-center">
                                  <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                          stroke-width="4"></circle>
                                      <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                      </path>
                                  </svg>
                                  AI is thinking...
                              </div>

                              <div v-if="aiError" class="text-red-400">
                                  AI Error: {{ aiError }}
                              </div>

                              <div v-if="!aiLoading && !aiError" class="space-y-2">
                                <div v-for="(question, index) in props.gameQuestions" :key="question.id" class="text-gray-300">
                                    <span class="font-medium">Q{{ index + 1 }}:</span>
                                    <span v-if="hasAIAnswered(index)" class="text-green-400 ml-2">
                                        {{ aiAnswers[index]?.answer || 'Answer not available' }}
                                        <span v-if="aiAnswers[index]?.score !== undefined && aiAnswers[index]?.score !== null" 
                                            class="text-blue-400 ml-1">
                                            (Score: {{ aiAnswers[index].score }})
                                        </span>
                                    </span>
                                    <span v-else-if="index < currentQuestionIndex" class="text-gray-500 ml-2">
                                        Waiting for AI...
                                    </span>
                                    <span v-else class="text-gray-500 ml-2">
                                        Not answered yet
                                    </span>
                                </div>
                              </div>

                              <!-- Debug info (remove in production) -->
                              <div v-if="playWithAI" class="mt-4 text-xs text-gray-500 bg-gray-900 p-2 rounded">
                                <p><strong>Debug AI Answers:</strong></p>
                                <pre>{{ JSON.stringify(debugAIAnswers, null, 2) }}</pre>
                            </div>
                          </div>

                          <div class="flex-1 min-w-[300px] p-4 bg-gray-800 rounded shadow">
                                <h3 class="font-semibold text-lg mb-2">AI Scores</h3>
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-700">
                                            <th class="p-2 border-b">Model</th>
                                            <th class="p-2 border-b">Game Session</th>
                                            <th class="p-2 border-b">Score</th>
                                            <th class="p-2 border-b">Date Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="score in aiScores" :key="score.id">
                                            <td class="p-2 border-b text-white">Normal</td>
                                            <td class="p-2 border-b text-white">{{ score.session_id }}</td>
                                            <td class="p-2 border-b text-white">{{ score.score }}</td>
                                            <td class="p-2 border-b text-white">{{ formatDate(score.created_at) }}</td>
                                        </tr>
                                        <tr v-if="aiScores.length === 0">
                                            <td colspan="4" class="p-2 text-center text-gray-400">No scores available
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <DynamicPagination :currentPage="aiScoresCurrentPage" :totalPages="aiScoresTotalPages"
                                    @change-page="changeAIScoresPage" />
                            </div>
                      </div>


                        <div class="flex flex-wrap gap-6 w-full">
                            <div class="min-w-[300px] basis-1/4 p-4 bg-gray-800 rounded shadow">
                                <h3 class="font-semibold text-lg mb-2">Players In Game</h3>

                                <div class="mb-2 text-gray-300 font-semibold">
                                    Players: {{ playersCount }} / {{ maxPlayers }}
                                </div>

                                <div v-if="maxPlayersReached"
                                    class="mb-2 p-2 bg-red-700 bg-red-800 text-red-100 rounded text-center font-bold">
                                    Max Players Reached
                                </div>

                                <ul class="list-disc pl-5">
                                    <li v-for="user in players" :key="user.id">{{ user.name }}</li>
                                </ul>

                                <div v-if="players.length === 0" class="text-gray-400 mt-2">
                                    Waiting for players to join...
                                </div>
                            </div>

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
                                            <td colspan="4" class="p-2 text-center text-gray-400">No scores available
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <DynamicPagination :currentPage="scoresCurrentPage" :totalPages="scoresTotalPages"
                                    @change-page="changeScoresPage" />
                            </div>
                        </div>

                        <div class="flex flex-col lg:flex-row gap-6 w-full">
                            <div class="w-full lg:w-1/2 lg:max-w-[50%] overflow-hidden">
                                <GameHeatmapComponent ref="gameHeatmapRef" :gameId="gameId" :gameQuestions="gameQuestions" />
                            </div>

                            <div class="w-full lg:w-1/2 lg:max-w-[50%] overflow-hidden">
                                <GameGraphComponent ref="gameGraphRef" :gameId="gameId" />
                            </div>
                        </div>


                        </div>
                </div>
            </div>
        </GameAuthenticatedLayout>
    </BreezeAuthenticatedLayout>
</template>


<style scoped>

.fade-slide-y-enter-from,
.fade-slide-y-leave-to {
    opacity: 0;
    transform: translateY(-10px);
    max-height: 0; /* Animate height from 0 */
    padding-top: 0;
    padding-bottom: 0;
    margin-top: 0;
    margin-bottom: 0;
}

.fade-slide-y-enter-to,
.fade-slide-y-leave-from {
    opacity: 1;
    transform: translateY(0);
    max-height: 500px; /* A value larger than the max possible height of the content */
    /* Restore original padding/margin if they were removed in -from state */
    padding-top: theme('padding.6'); /* or whatever your original padding-top was, e.g., p-6 means 1.5rem */
    padding-bottom: theme('padding.6');
    margin-top: theme('margin.4'); /* mt-4 */
    margin-bottom: 0; /* if no mb */
}
</style>