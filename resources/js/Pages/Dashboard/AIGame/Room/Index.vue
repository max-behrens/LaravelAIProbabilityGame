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
    difficulties: Array,
    categories: Array,
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

const selectedDifficulty = ref(1); // Default to Easy (id: 1)
const selectedCategory = ref(1);   // Default to Number (id: 1)
const currentGameQuestions = ref([]);

// Initialize player interactions first
const {
    players,
    flashMessages,
    gameState,
    isInGame,
    preSubmittedAnswers,
    preAnsweredQuestions,
    preReadyPlayers,
    isWaitingToStart,
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
    currentQuestionIndex: currentQuestionIndex.value,
    currentGameQuestions: currentGameQuestions.value,
    selectedDifficulty: selectedDifficulty.value,
    selectedCategory: selectedCategory.value
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
    return currentQuestionIndex.value === currentGameQuestions.value.length - 1;
});

// Watch for game state changes
const isWaitingForOthers = computed(() => gameState.value.waitingForOthers);
const isGameInProgress = computed(() => gameState.value.gameInProgress);

// New computed property for controlling question input visibility
const showQuestionInput = computed(() => {
    return isGameStarted.value && !isWaitingForOthers.value && !gameIsOver.value && currentGameQuestions.value.length > 0;
});

console.log('QUESTIONS: ' + JSON.stringify(currentGameQuestions.value));

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
        
        // ADD THESE LINES - Include difficulty and category parameters
        if (selectedDifficulty.value) {
            params.set('difficulty_id', selectedDifficulty.value);
        }
        if (selectedCategory.value) {
            params.set('category_id', selectedCategory.value);
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
        
        if (selectedDifficulty.value) {
            params.set('difficulty_id', selectedDifficulty.value);
        }
        if (selectedCategory.value) {
            params.set('category_id', selectedCategory.value);
        }
        
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


const fetchGameQuestions = async (difficultyId = null, categoryId = null) => {
    try {
        const difficulty = difficultyId || selectedDifficulty.value;
        const category = categoryId || selectedCategory.value;

        const urlParams = new URLSearchParams(window.location.search);

        if (difficulty) {
            urlParams.set('difficulty_id', difficulty);
        }
        if (category) {
            urlParams.set('category_id', category);
        }
        
        const response = await axios.get(`/api/games/${props.gameId}/questions?${urlParams.toString()}`);

        console.log('difficultyId: ' + difficultyId);
        
        currentGameQuestions.value = response.data.questions;
        console.log('Fetched questions:', currentGameQuestions.value);
    } catch (error) {
        console.error('Failed to fetch game questions:', error);
        errorMessage.value = 'Failed to load game questions.';
    }
};

const onDifficultyOrCategoryChange = async () => {
    console.log('Difficulty/Category changed:', selectedDifficulty.value, selectedCategory.value);
    
    // 1. Fetch the new questions and scores
    await fetchGameQuestions(selectedDifficulty.value, selectedCategory.value);
    await fetchGameScores(1);
    await fetchAIScores(1);

    // 2. Update the URL in the browser's address bar
    const url = new URL(window.location);
    url.searchParams.set('difficulty_id', selectedDifficulty.value);
    url.searchParams.set('category_id', selectedCategory.value);

    // Use pushState to change the URL without reloading the page
    window.history.pushState({}, '', url);

    console.log('URL updated to:', url.toString());
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

const getDifficultyName = (difficultyId) => {
    const difficulty = props.difficulties.find(d => d.id === difficultyId);
    return difficulty ? difficulty.name : 'Unknown';
};

const getCategoryName = (categoryId) => {
    const category = props.categories.find(c => c.id === categoryId);
    return category ? category.name : 'Unknown';
};

// Updated game control functions
const startGame = async () => {

    currentQuestionIndex.value = 0;
    answers.value = [];


    try {
        console.log('Starting game with player count:', playerCount.value);
        // Check if this is a multiplayer attempt and validate other players' settings
        const playerCountNum = parseInt(playerCount.value);
        
        // Check if this is a multiplayer attempt and validate other players' settings
        if (playerCountNum > 1) {
            console.log('Starting multiplayer game with 2 players...');
            // Check if any other players in the game have their player count set to 2
            const response = await axios.get(`/games/${props.gameId}/validate-multiplayer-start`);

            console.log('Multiplayer start validation response:', JSON.stringify(response.data));
            console.log('Can start multiplayer:', response.data.canStartMultiplayer);
            
            if (!response.data.canStartMultiplayer) {
                addFlashMessage(
                    'Cannot start multiplayer game. At least one other player must also have "2 Players" selected to start a multiplayer game.', 
                    'warning'
                );
                return;
            }
        }

        // Reset AI state
        resetAI();

        // Reset session before starting new game
        await axios.post(`/games/${props.gameId}/reset-session`);
        
        const response = await axios.post(`/games/${props.gameId}/player-ready`, {
            userId: props.auth.user.id,
            userName: props.auth.user.name,
            requiredCount: playerCount.value,
            difficulty_id: selectedDifficulty.value,
            category_id: selectedCategory.value,
            play_with_ai: playWithAI.value,
        });

        console.log('playerCount.value: ' + playerCount.value);

        if (response.data.status === 'waiting') {
            if (playerCount.value === 1) {
                // Single player - start immediately
                if (showSinglePlayerAIWarning.value) {
                    addFlashMessage('Cannot start game. Please enable "Play with AI" or add more players.', 'warning');
                    return;
                } else {
                    isGameStarted.value = true;
                    gameIsOver.value = false;
                    addFlashMessage('Game started!', 'success');
                    
                    // Broadcast single-player game start to other players
                    try {
                        await axios.post(`/api/games/${props.gameId}/broadcast`, {
                            event: 'game.started.single',
                            data: {
                                userId: props.auth.user.id,
                                userName: props.auth.user.name,
                                playerCount: 1,
                                difficulty_id: selectedDifficulty.value,
                                category_id: selectedCategory.value,
                                play_with_ai: playWithAI.value,
                                timestamp: new Date().toISOString()
                            }
                        });
                        console.log('Single-player game start broadcasted to other players');
                    } catch (error) {
                        console.error('Failed to broadcast single-player start:', error);
                        // Don't fail the game start if broadcast fails
                    }
                }
            } else {
                // Multiplayer - store ready state and wait for others
                preReadyPlayers.value.add(props.auth.user.id);
                isWaitingToStart.value = true;
                addFlashMessage('You are ready! Waiting for other players to start...', 'success');
            }
        } else if (response.data.status === 'started') {
            // All players are ready - apply the game settings and start immediately
            if (response.data.gameSettings) {
                await applyGameSettings(response.data.gameSettings);
            }
            
            if (showSinglePlayerAIWarning.value) {
                addFlashMessage('Cannot start game. Please enable "Play with AI" or add more players.', 'warning');
                return;
            } else {
                isGameStarted.value = true;
                gameIsOver.value = false;
                isWaitingToStart.value = false;
                preReadyPlayers.value.clear();
                addFlashMessage('All players ready! Game started!', 'success');
            }
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Failed to signal readiness.';
        console.error(error);
    }
};

const applyGameSettings = async (settings) => {
    console.log('Applying game settings from ready player:', settings);
    
    // Update local settings
    selectedDifficulty.value = settings.difficulty_id;
    selectedCategory.value = settings.category_id;
    playWithAI.value = settings.play_with_ai;
    
    
    // Update questions if provided
    if (settings.questions && settings.questions.length > 0) {
        currentGameQuestions.value = settings.questions;
        console.log('Questions updated from game settings:', settings.questions.length, 'questions');
    } else {
        // Fallback: fetch questions for these settings
        await fetchGameQuestions(settings.difficulty_id, settings.category_id);
    }
    
    // Update URL to reflect new settings
    const url = new URL(window.location);
    url.searchParams.set('difficulty_id', settings.difficulty_id);
    url.searchParams.set('category_id', settings.category_id);
    window.history.pushState({}, '', url);


    addFlashMessage(`Game settings updated to match ${settings.starter_name}'s preferences!`, 'info');
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

// Handle player count changes - FIXED to ensure numeric value
const onPlayerCountChange = async (newCount) => {
    // Convert to number to ensure consistent type
    const numericCount = parseInt(newCount);
    playerCount.value = numericCount;
    
    console.log('Player count changed to:', numericCount, 'Type:', typeof numericCount);
    
    if (isInGame.value) {
        await changePlayerCount(numericCount);
    }
};

const nextOrSubmit = async () => {
    if (!isLastQuestion.value) {
        // ENHANCED: Use the new answerQuestion function with auto-progression
        const result = await answerQuestion(currentQuestionIndex.value, answers.value[currentQuestionIndex.value], playerCount.value);

        if (result.submitted) {
            // Answer was submitted successfully (single player or all players answered)
            // Check if we're playing with AI and need to wait for AI answer
            if (playWithAI.value && currentGameQuestions.value[currentQuestionIndex.value]) {
                // For single player: Get AI answer immediately
                if (playerCount.value === 1) {
                    console.log('Single player: Requesting AI answer for question:', currentQuestionIndex.value);
                    await getAIAnswerForQuestion(
                        currentGameQuestions.value[currentQuestionIndex.value].question,
                        props.gameId,
                        currentQuestionIndex.value,
                        selectedDifficulty.value,
                        selectedCategory.value
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
                            currentGameQuestions.value[currentQuestionIndex.value].question,
                            props.gameId,
                            currentQuestionIndex.value,
                            selectedDifficulty.value,
                            selectedCategory.value
                        );
                        
                        // Broadcast that AI has answered and all players can progress
                        await axios.post(`/api/games/${props.gameId}/broadcast`, {
                            event: 'ai.answered',
                            data: {
                                questionIndex: currentQuestionIndex.value,
                                aiAnswer: aiAnswers.value[currentQuestionIndex.value]?.answer,
                                aiScore: aiAnswers.value[currentQuestionIndex.value]?.score,
                                isCorrect: aiAnswers.value[currentQuestionIndex.value]?.isCorrect,
                                aiData: aiAnswers.value[currentQuestionIndex.value], // Send complete AI object
                                gameId: props.gameId,
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
            // For single-player: Get AI answer for final question if needed
            if (playerCount.value === 1 && playWithAI.value) {
                if (!hasAIAnswered(currentQuestionIndex.value)) {
                    addFlashMessage('Getting AI answer for final question...', 'info');
                    console.log('Single-player: Getting AI answer for final question:', currentQuestionIndex.value);
                    await getAIAnswerForQuestion(
                        currentGameQuestions.value[currentQuestionIndex.value].question,
                        props.gameId,
                        currentQuestionIndex.value,
                        selectedDifficulty.value,
                        selectedCategory.value
                    );
                }
            }
            
            // For multiplayer: Check if AI needs to answer
            if (playerCount.value > 1 && playWithAI.value) {
                if (!hasAIAnswered(currentQuestionIndex.value)) {
                    addFlashMessage('Waiting for AI to answer the final question...', 'info');
                    console.log('Multiplayer: Triggering AI answer for final question:', currentQuestionIndex.value);
                    await getAIAnswerForQuestion(
                        currentGameQuestions.value[currentQuestionIndex.value].question,
                        props.gameId,
                        currentQuestionIndex.value,
                        selectedDifficulty.value,
                        selectedCategory.value
                    );
                }
            }

            const submissionAnswers = answers.value.map(answer => answer);

            const result = await submitAnswers(
                submissionAnswers, 
                playerCount.value, 
                selectedDifficulty.value, 
                selectedCategory.value
            );

            if (result.submitted) {
                addFlashMessage('Answers submitted successfully! Game Completed.', 'success');
                gameIsOver.value = true;
                isGameStarted.value = false;
                
                // CRITICAL FIX: For single-player, broadcast game completion immediately
                if (playerCount.value === 1) {
                    try {
                        await axios.post(`/api/games/${props.gameId}/broadcast`, {
                            event: 'game.completed.single',
                            data: {
                                userId: props.auth.user.id,
                                userName: props.auth.user.name,
                                timestamp: new Date().toISOString(),
                                difficultyId: selectedDifficulty.value,
                                categoryId: selectedCategory.value,
                                playedWithAI: playWithAI.value
                            }
                        });
                        console.log('Single-player game completion broadcasted');
                    } catch (error) {
                        console.error('Failed to broadcast single-player completion:', error);
                    }
                }

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
    console.log('Resetting game state - before reset:', {
        currentQuestionIndex: currentQuestionIndex.value,
        answersLength: answers.value.length,
        isGameStarted: isGameStarted.value,
        gameIsOver: gameIsOver.value
    });
    
    // CRITICAL: Reset question index to 0
    currentQuestionIndex.value = 0;
    
    // Clear all answers
    answers.value = [];
    
    // Reset game state flags
    isGameStarted.value = false;
    gameIsOver.value = false;
    isWaitingToStart.value = false;
    preReadyPlayers.value.clear();
    gameState.value.waitingForOthers = false;
    gameState.value.playersReady.clear();
    
    console.log('Game state fully reset - after reset:', {
        currentQuestionIndex: currentQuestionIndex.value,
        answersLength: answers.value.length,
        isGameStarted: isGameStarted.value,
        gameIsOver: gameIsOver.value
    });
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
    
    const difficultyId = urlParams.get('difficulty_id');
    if (difficultyId && !isNaN(parseInt(difficultyId))) {
        selectedDifficulty.value = parseInt(difficultyId);
    } else {
        selectedDifficulty.value = 1; // Force default to 1 if URL param is invalid/missing
    }
    
    const categoryId = urlParams.get('category_id');
    if (categoryId && !isNaN(parseInt(categoryId))) {
        selectedCategory.value = parseInt(categoryId);
    } else {
        selectedCategory.value = 1; // Force default to 1 if URL param is invalid/missing
    }
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
    
    // Fetch initial game questions with default difficulty and category
    fetchGameQuestions();

    // Vertical Nav:
    window.addEventListener('scroll', updateNavigation);

    // Parse initial filters from URL
    parseFiltersFromUrl();
    
    // Listen for filter changes from GameAuthenticated layout
    window.addEventListener('gameFiltersChanged', handleFilterChange);

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
        onQuestionProgress: async (questionIndex, allAnswers = null) => {
            console.log('🔄 Received question progress event for question:', questionIndex);
            
            if (allAnswers === null) {
                if (questionIndex === currentQuestionIndex.value) {
                    currentQuestionIndex.value++;
                    addFlashMessage('Moving to next question!', 'success');
                }
            } else {
                if (questionIndex === currentQuestionIndex.value) {
                    if (playWithAI.value && !hasAIAnswered(questionIndex)) {
                        console.log('All players answered, waiting for AI...');
                        addFlashMessage('All players answered! Waiting for AI...', 'info');
                    } else {
                        currentQuestionIndex.value++;
                        addFlashMessage('All players answered! Moving to next question!', 'success');
                    }
                }
            }
        },
        onAutoStart: () => {
            console.log('🚀 Auto-starting game via callback...');
            
            if (playerCount.value === 1 && !playWithAI.value) {
                addFlashMessage('Cannot start game. Please enable "Play with AI" or add more players.', 'warning');
                return;
            }
            
            isGameStarted.value = true;
            gameIsOver.value = false;
            isWaitingToStart.value = false;
            preReadyPlayers.value.clear();
            
            addFlashMessage('All players ready! Your game has started automatically!', 'success');
        },
        // NEW: Add callback for applying game settings
        onApplyGameSettings: async (settings) => {
            console.log('🔄 Applying game settings via callback:', settings);
            await applyGameSettings(settings);
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
                                Question {{ currentQuestionIndex + 1 }} / {{ currentGameQuestions.length }}
                            </div>
                            <div class="text-center mb-4 text-white text-xl font-semibold">
                                {{ currentGameQuestions[currentQuestionIndex]?.question }}
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

                        <!-- Single Player Game In Progress Indicator -->
                        <div v-if="gameState.gameInProgress" 
                            class="basis-full mb-6 text-center">
                            <div class="p-6 bg-orange-900 text-orange-200 rounded-lg border border-orange-700">
                                <div class="flex items-center justify-center mb-4">
                                    <svg class="animate-spin h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <h3 class="text-xl font-bold">Game In Progress</h3>
                                </div>
                                <p class="text-lg mb-2">A player is currently playing a single-player game in this room.</p>
                                <p class="text-sm opacity-90">Please wait for them to finish before starting your own game.</p>
                                <div class="mt-4 text-xs bg-orange-800 bg-opacity-50 rounded p-2">
                                    You can still view scores and statistics while waiting.
                                </div>
                            </div>
                        </div>

                        <!-- Game Setup Section -->
                        <div class="basis-full flex flex-col gap-6 p-4 bg-gray-800 rounded shadow text-white">

                            <!-- Row 1: Game Name & Buttons -->
                            <div class="flex items-center justify-center flex-wrap gap-4 min-h-[32px]">
                                <h2 class="text-xl text-white font-semibold">
                                    Lobby {{ game.id }}: {{ gameType.name || 'Unknown Game' }}
                                </h2>

                                
                                <button @click="startGame"
                                    :disabled="!isInGame || (isGameInProgress && !gameIsOver) || isWaitingToStart"
                                    class="bg-green-900 hover:bg-green-800 text-green-200 font-bold py-2 px-4 rounded transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ isWaitingToStart ? 'Ready - Waiting...' : (isWaitingForOthers ? 'Waiting...' : 'Start Game') }}
                                </button>

                                <!-- Waiting indicator for multiplayer start -->
                                <div v-if="isWaitingToStart && !gameIsOver && !isGameStarted" class="basis-full mb-6 text-center">
                                    <div class="p-4 bg-blue-900 text-blue-200 rounded border border-blue-700">
                                        <p class="text-lg font-semibold">You are ready to start!</p>
                                        <p class="text-sm mt-2">Waiting for other players to click "Start Game"...</p>
                                        <div class="mt-2 text-xs text-blue-300">
                                            Ready players: {{ gameState.playersReady.size + 1 }} / {{ playerCount }}
                                        </div>
                                    </div>
                                </div>

                                <!-- NEW: Waiting indicator for when others are ready but I'm not -->
                                <div v-if="!isWaitingToStart && !isGameStarted && gameState.playersReady.size > 0 && !gameIsOver && !gameState.waitingForOthers" class="basis-full mb-6 text-center">
                                    <div class="p-4 bg-yellow-900 text-yellow-200 rounded border border-yellow-700">
                                        <div class="text-lg font-semibold mb-3">
                                            {{ gameState.starterName }} is ready to start!
                                        </div>
                                        
                                        <div class="text-sm mb-4">
                                            They have set the game to these settings:
                                        </div>
                                        
                                        <div class="bg-yellow-800 bg-opacity-50 rounded p-3 mb-4 text-sm">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                                <div>
                                                    <span class="font-semibold">Difficulty:</span> 
                                                    {{ getDifficultyName(gameState.gameSettings?.difficulty_id) }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold">Category:</span> 
                                                    {{ getCategoryName(gameState.gameSettings?.category_id) }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold">Play with AI:</span> 
                                                    {{ gameState.gameSettings?.play_with_ai ? 'Yes' : 'No' }}
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-sm mb-4">
                                            <strong>Click "Start Game" to join with these settings!</strong>
                                        </div>
                                        
                                        <div class="text-xs text-yellow-300">
                                            Ready players: {{ gameState.playersReady.size }} / {{ playerCount }}
                                        </div>
                                    </div>
                                </div>

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

                            <!-- Row 2: Settings -->
                            <div class="flex flex-wrap justify-center items-center gap-6">
                                <!-- Number of Players -->
                                <div class="flex items-center gap-2">
                                    <label for="players">Number of Players:</label>
                                    <select id="players" :value="playerCount" @change="onPlayerCountChange($event.target.value)"
                                        :disabled="isGameInProgress || isWaitingForOthers || gameIsOver"
                                        class="border rounded px-2 py-1 bg-gray-700 text-white disabled:opacity-50">
                                        <option value="1">1 Player</option>
                                        <option value="2">2 Players</option>
                                    </select>
                                </div>

                                <!-- Difficulty -->
                                <div class="flex items-center gap-2">
                                    <label for="difficulty">Difficulty:</label>
                                    <select id="difficulty" v-model="selectedDifficulty"
                                        :disabled="isGameInProgress || isWaitingForOthers || gameIsOver"
                                        @change="onDifficultyOrCategoryChange"
                                        class="border rounded px-2 py-1 bg-gray-700 text-white disabled:opacity-50">
                                        <option v-for="difficulty in difficulties" :key="difficulty.id" :value="difficulty.id">
                                        {{ difficulty.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Category -->
                                <div class="flex items-center gap-2">
                                    <label for="category">Category:</label>
                                    <select id="category" v-model="selectedCategory"
                                        :disabled="isGameInProgress || isWaitingForOthers || gameIsOver"
                                        @change="onDifficultyOrCategoryChange"
                                        class="border rounded px-2 py-1 bg-gray-700 text-white disabled:opacity-50">
                                        <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Play with AI -->
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" v-model="playWithAI"
                                        :disabled="isGameInProgress || isWaitingForOthers || gameIsOver" />
                                    <label>Play with AI</label>
                                </div>
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
                                    <div v-for="(question, index) in currentGameQuestions" :key="question.id" class="text-gray-300">
                                        <span class="font-medium">Q{{ index + 1 }}:</span>
                                        <span v-if="hasAIAnswered(index)" class="text-green-400 ml-2">
                                            {{ aiAnswers[index]?.answer || 'Answer not available' }}
                                            <span v-if="aiAnswers[index]?.score !== undefined && aiAnswers[index]?.score !== null" 
                                                class="text-blue-400 ml-1">
                                                (Score: {{ aiAnswers[index].score }})
                                            </span>
                                        </span>
                                        <span v-else-if="index < currentQuestionIndex || gameIsOver" class="text-gray-500 ml-2">
                                            <!-- Show different text for completed games -->
                                            {{ gameIsOver ? 'Game completed' : 'Waiting for AI...' }}
                                        </span>
                                        <span v-else class="text-gray-500 ml-2">
                                            Not answered yet
                                        </span>
                                    </div>
                                </div>

                                <div v-if="playWithAI" class="mt-4 text-xs text-gray-500 bg-gray-900 p-2 rounded">
                                    <p><strong>Debug AI Answers:</strong></p>
                                    <div v-for="(answer, index) in aiAnswers" :key="index" class="mb-1">
                                        Q{{ parseInt(index) + 1 }}: 
                                        {{ answer?.answer || 'No answer' }} 
                                        (Score: {{ answer?.score ?? 'No score' }})
                                        (Cached: {{ answer?.cached ? 'Yes' : 'No' }})
                                    </div>
                                    <p class="mt-2"><strong>Current Question Index:</strong> {{ currentQuestionIndex }}</p>
                                    <p><strong>Game Is Over:</strong> {{ gameIsOver }}</p>
                                    <p><strong>Total Questions:</strong> {{ currentGameQuestions.length }}</p>
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
                                <GameHeatmapComponent ref="gameHeatmapRef" :gameId="gameId" :gameQuestions="currentGameQuestions" />
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