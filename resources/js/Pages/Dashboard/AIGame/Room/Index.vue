<script setup>
import { ref, defineProps, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import GameAuthenticatedLayout from '@/Layouts/GameAuthenticated.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import { useAI, createAI } from '@/Composables/useAI';
import { createBespokeAI } from '@/Composables/useBespokeAI';
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
const notInActiveGame = ref(false);
const gameIsOver = ref(false); // Flag to indicate game is finished, but not necessarily reset yet
const gameGraphRef = ref(null);
const gameHeatmapRef = ref(null);
const showVerticalNav = ref(false);
const isNavigatingProgrammatically = ref(false);
const currentNavSection = ref(0);
const excludeAI = ref(true);
const difficultyId = ref(null);
const categoryId = ref(null);

const selectedDifficulty = ref(1); // Default to Easy (id: 1)
const selectedCategory = ref(1);   // Default to Number (id: 1)
const currentGameQuestions = ref([]);

const playerSearchQuery = ref('');
const aiSearchQuery = ref('');
const playerSortField = ref('created_at');
const playerSortDirection = ref('desc');
const aiSortField = ref('created_at');
const aiSortDirection = ref('desc');

const playWithAISection = ref(false);

const isStealAnimating = ref(false);
const hasNonLeaderSubmitted = ref(false);

const joinTeamWithPlayers = ref(false);
const joinTeamWithAI = ref(false);
const suggestionInput = ref('');
const isTeamMode = ref(false);
const teamLeaderName = ref('');

// Team selection state for 3+ players without AI
const selectedTeam = ref(1); // 1 or 2
const lobbyTeamLeader = ref(null);
const isLobbyTeamLeader = ref(false);
const team1Players = ref([]);
const team2Players = ref([]);


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
    team1Leader,
    team2Leader,
    playerTeamAssignments,
    fetchPlayers,
    changePlayerCount,
    answerQuestion,
    submitAnswers,
    addFlashMessage,
    removeFlashMessage,
    clearFlashMessages,
    registerCallbacks,
    setAIModule, // Get the setAIModule function
    setBespokeAIModule
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

// Initialize bespoke AI alongside existing AI
const bespokeAIModule = createBespokeAI(props.gameId, () => ({
    gameState: gameState.value,
    players: players.value,
    currentQuestionIndex: currentQuestionIndex.value,
    currentGameQuestions: currentGameQuestions.value,
    selectedDifficulty: selectedDifficulty.value,
    selectedCategory: selectedCategory.value
}));

const {
    bespokeAIAnswers,
    bespokeAILoading,
    bespokeAIError,
    playWithBespokeAI,
    selectedAIModel,
    availableModels,
    modelStats,
    loadAvailableModels,
    getBespokeAIAnswer,
    getBespokeAIAnswerForQuestion,
    hasBespokeAIAnswered,
    resetBespokeAI,
    changeAIModel
} = bespokeAIModule;

setBespokeAIModule(bespokeAIModule);

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

const isTeamLeader = computed(() => {
    // Lobby team leader mode
    if (shouldShowLobbyTeamLeader.value) {
        return isLobbyTeamLeader.value;
    }
    
    // Team selection mode (3+ players without AI)
    if (shouldShowTeamSelection.value) {
        return isTeam1Leader.value || isTeam2Leader.value;
    }
    
    // Existing team modes
    if (joinTeamWithPlayers.value || joinTeamWithAI.value) {
        return isTeamLeader.value; // Use the existing logic
    }
    
    return false;
});

// Watch for game state changes
const isWaitingForOthers = computed(() => gameState.value.waitingForOthers);
const isGameInProgress = computed(() => gameState.value.gameInProgress);

const isTeam1Leader = computed(() => {
    return selectedTeam.value === 1 && team1Leader.value === props.auth.user.name;
});

const isTeam2Leader = computed(() => {
    return selectedTeam.value === 2 && team2Leader.value === props.auth.user.name;
});

const canBecomeTeam1Leader = computed(() => {
    return selectedTeam.value === 1 && 
           !team1Leader.value && 
           !isWaitingForOthers.value && 
           !gameIsOver.value;
});

const canBecomeTeam2Leader = computed(() => {
    return selectedTeam.value === 2 && 
           !team2Leader.value && 
           !isWaitingForOthers.value && 
           !gameIsOver.value;
});

const canUnselectTeam1Leader = computed(() => {
    return team1Leader.value === props.auth.user.name && 
           !isWaitingForOthers.value && 
           !gameIsOver.value;
});

const canUnselectTeam2Leader = computed(() => {
    return team2Leader.value === props.auth.user.name && 
           !isWaitingForOthers.value && 
           !gameIsOver.value;
});

const isTeam1RadioDisabled = computed(() => {
    return isWaitingForOthers.value || 
           gameIsOver.value || 
           team2Leader.value === props.auth.user.name; // Disable if leader of other team
});

const isTeam2RadioDisabled = computed(() => {
    return isWaitingForOthers.value || 
           gameIsOver.value || 
           team1Leader.value === props.auth.user.name; // Disable if leader of other team
});

// New computed property for controlling question input visibility
const showQuestionInput = computed(() => {
    // Base condition: game started, not waiting, not over, and has questions
    const baseCondition = isGameStarted.value && 
                          !isWaitingForOthers.value && 
                          !gameIsOver.value && 
                          currentGameQuestions.value.length > 0;

    console.log('showQuestionInput:', {
        isGameStarted: isGameStarted.value,
        isWaitingForOthers: isWaitingForOthers.value,
        gameIsOver: gameIsOver.value,
        hasQuestions: currentGameQuestions.value.length > 0
    });
    
    if (!baseCondition) {
        return false;
    }
    
    // Single player: always show
    if (playerCount.value === 1) {
        return true;
    }
    
    // Multiplayer without team mode: show for everyone
    if (!isTeamMode.value) {
        return true;
    }
    
    // Team AI mode: show for everyone (all can submit)
    if (joinTeamWithAI.value) {
        return true;
    }
    
    // Default: show for everyone
    return true;
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

const fetchGameScores = async (page = 1, sortField = null, sortDirection = null) => {
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

        if (appliedFilters.value.difficultyId) {
            params.set('difficulty', appliedFilters.value.difficultyId);
        }
        if (appliedFilters.value.categoryId) {
            params.set('category', appliedFilters.value.categoryId);
        }
        
        // Add sorting parameters
        if (sortField || playerSortField.value) {
            params.set('sort_field', sortField || playerSortField.value);
            params.set('sort_direction', sortDirection || playerSortDirection.value);
        }
        
        const url = `/api/games/${props.gameId}/scores?${params.toString()}`;
        
        const response = await axios.get(url);
        gameScores.value = response.data.data;
        scoresTotalPages.value = response.data.last_page;
        scoresCurrentPage.value = response.data.current_page;
    } catch (error) {
        errorMessage.value = 'Failed to load player scores.';
        console.error(error);
    }
};

const fetchAIScores = async (page = 1, sortField = null, sortDirection = null) => {
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

        if (appliedFilters.value.difficultyId) {
            params.set('difficulty', appliedFilters.value.difficultyId);
        }
        if (appliedFilters.value.categoryId) {
            params.set('category', appliedFilters.value.categoryId);
        }
        
        // Add sorting parameters
        if (sortField || aiSortField.value) {
            params.set('sort_field', sortField || aiSortField.value);
            params.set('sort_direction', sortDirection || aiSortDirection.value);
        }
        
        const url = `/api/games/${props.gameId}/ai-scores?${params.toString()}`;
        
        const response = await axios.get(url);

        aiScores.value = response.data.data || []; // Ensure it's always an array

        aiScoresTotalPages.value = parseInt(response.data.last_page) || 1;
        aiScoresCurrentPage.value = parseInt(response.data.current_page) || 1;
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
        const difficulty = difficultyId || selectedDifficulty.value || 1;
        const category = categoryId || selectedCategory.value || 1;

        // Build URL with difficulty and category as route parameters
        const url = `/api/games/${props.gameId}/${difficulty}/${category}/questions`;
        
        const response = await axios.get(url);

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
    
    // 1. Fetch the new questions and scores (maintaining current sort)
    await fetchGameQuestions(selectedDifficulty.value, selectedCategory.value);
    await fetchGameScores(1, playerSortField.value, playerSortDirection.value);
    await fetchAIScores(1, aiSortField.value, aiSortDirection.value);

    updateGameSettings();
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
    return playerCount.value === 1 && !playWithAI.value && !playWithBespokeAI.value;
});

const validateTeamSetup = () => {
    if (shouldShowTeamSelection.value) {
        // Validate team selection mode
        if (!selectedTeam.value) {
            addFlashMessage('Please select a team before starting!', 'warning');
            return false;
        }
        
        // // Check if the current user is the team leader for their selected team
        // const isValidLeader = (selectedTeam.value === 1 && team1Leader.value === props.auth.user.name) ||
        //                     (selectedTeam.value === 2 && team2Leader.value === props.auth.user.name);
        
        // if (!isValidLeader) {
        //     const teamName = selectedTeam.value === 1 ? 'Team 1' : 'Team 2';
        //     addFlashMessage(`${teamName} needs a leader before starting! Click "Become ${teamName} Leader" first.`, 'warning');
        //     return false;
        // }
    }
    
    if (shouldShowLobbyTeamLeader.value) {
        // Validate lobby team leader mode
        if (!lobbyTeamLeader.value) {
            addFlashMessage('Please select a lobby team leader before starting!', 'warning');
            return false;
        }
    }
    
    return true;
};


// Updated game control functions
const startGame = async () => {
    currentQuestionIndex.value = 0;
    answers.value = [];

    if (!validateTeamSetup()) {
        return; // Stop if team setup is invalid
    }

    try {
        console.log('Starting game with player count:', playerCount.value);
        
        // Determine team leadership based on game mode
        if (playerCount.value > 1) {
            if (shouldShowLobbyTeamLeader.value) {
                // Lobby team leader mode: only lobby team leader can start
                if (!isLobbyTeamLeader.value) {
                    addFlashMessage('Only the lobby team leader can start the game!', 'warning');
                    return;
                }
                isTeamMode.value = true;
                teamLeaderName.value = props.auth.user.name;
            } else if (shouldShowTeamSelection.value) {
                // Team selection mode: only team leaders can start
                // FIXED: Check if current user is actually a team leader
                // const isValidTeamLeader = (selectedTeam.value === 1 && team1Leader.value === props.auth.user.name) ||
                //                         (selectedTeam.value === 2 && team2Leader.value === props.auth.user.name);
                
                // if (!isValidTeamLeader) {
                //     const requiredLeader = selectedTeam.value === 1 ? 'Team 1' : 'Team 2';
                //     addFlashMessage(`Only the ${requiredLeader} leader can start the game!`, 'warning');
                //     return;
                // }
                isTeamMode.value = true;
                teamLeaderName.value = props.auth.user.name;
                isTeamLeader.value = true; // Set this explicitly
            } else if (joinTeamWithPlayers.value || joinTeamWithAI.value) {
                // Existing team modes
                isTeamLeader.value = true;
                teamLeaderName.value = props.auth.user.name;
                isTeamMode.value = true;
            }
        }
        
        const playerCountNum = parseInt(playerCount.value);
        
        if (playerCountNum > 1) {
            console.log('Starting multiplayer game...');
            const response = await axios.get(`/games/${props.gameId}/validate-multiplayer-start`);

            if (!response.data.canStartMultiplayer) {
                addFlashMessage(
                    'Cannot start multiplayer game. At least one other player must also have the same player count selected.', 
                    'warning'
                );
                return;
            }
        }

        resetAI();
        resetBespokeAI();

        await axios.post(`/games/${props.gameId}/reset-session`);
        
        // Enhanced player ready request with new team data
        const readyData = {
            userId: props.auth.user.id,
            userName: props.auth.user.name,
            requiredCount: playerCount.value,
            difficulty_id: selectedDifficulty.value,
            category_id: selectedCategory.value,
            play_with_ai: playWithAI.value,
            play_with_bespoke_ai: playWithBespokeAI.value,
            selected_ai_model: selectedAIModel.value,
            join_team_with_players: joinTeamWithPlayers.value,
            join_team_with_ai: joinTeamWithAI.value,
            isTeamLeader: isTeamLeader.value,
            // New team management data
            lobbyTeamLeader: shouldShowLobbyTeamLeader.value,
            isLobbyTeamLeader: isLobbyTeamLeader.value,
            selectedTeam: selectedTeam.value,
            isTeam1Leader: isTeam1Leader.value,
            isTeam2Leader: isTeam2Leader.value
        };
        
        const response = await axios.post(`/games/${props.gameId}/player-ready`, readyData);

        // Rest of existing startGame logic...
        if (response.data.status === 'waiting') {
            if (playerCount.value === 1) {
                if (showSinglePlayerAIWarning.value) {
                    addFlashMessage('Cannot start game. Please enable "Play With ChatGPT" or "Play With Learning Model" or add more players.', 'warning');
                    return;
                } else {
                    isGameStarted.value = true;
                    gameIsOver.value = false;
                    addFlashMessage('Game started!', 'success');
                    
                    // Broadcast single player start...
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
                                play_with_bespoke_ai: playWithBespokeAI.value,
                                selected_ai_model: selectedAIModel.value,
                                join_team_with_players: joinTeamWithPlayers.value,
                                join_team_with_ai: joinTeamWithAI.value,
                                timestamp: new Date().toISOString()
                            }
                        });
                    } catch (error) {
                        console.error('Failed to broadcast single-player start:', error);
                    }
                }
            } else {
                preReadyPlayers.value.add(props.auth.user.id);
                isWaitingToStart.value = true;
                addFlashMessage('You are ready! Waiting for other players to start...', 'success');
            }
        } else if (response.data.status === 'started') {
            if (response.data.gameSettings) {
                await applyGameSettings(response.data.gameSettings);
            }
            
            if (showSinglePlayerAIWarning.value) {
                addFlashMessage('Cannot start game. Please enable "Play With ChatGPT" or "Play With Learning Model" or add more players.', 'warning');
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

const getDisabledInputPlaceholder = () => {
    if (shouldShowLobbyTeamLeader.value) {
        return 'Only lobby team leader can answer';
    } else if (shouldShowTeamSelection.value) {
        return `Only Team ${selectedTeam.value} leader can answer`;
    } else if (joinTeamWithPlayers.value) {
        return 'Only team leader can answer';
    } else if (joinTeamWithAI.value && playerCount.value === 2) {
        return 'Only lobby team leader can answer';
    }
    return 'Only team leader can answer';
};

const getWaitingMessage = () => {
    if (shouldShowLobbyTeamLeader.value) {
        return `Waiting for ${lobbyTeamLeader.value} to answer...`;
    } else if (shouldShowTeamSelection.value) {
        const leaderName = selectedTeam.value === 1 ? team1Leader.value : team2Leader.value;
        return `Waiting for Team ${selectedTeam.value} leader${leaderName ? ` (${leaderName})` : ''} to answer...`;
    } else if (joinTeamWithPlayers.value) {
        return `Waiting for ${teamLeaderName.value} to answer...`;
    } else if (joinTeamWithAI.value && playerCount.value === 2) {
        return 'Waiting for lobby team leader to answer...';
    }
    return 'Waiting for team leader to answer...';
};

const handleTeamAI2PlayerLogic = () => {
    if (joinTeamWithAI.value && playerCount.value === 2) {
        // In 2-player TeamAI mode, the non-host automatically becomes lobby team leader
        // This will be handled by the applyGameSettings when the non-host receives the ready event
        console.log('TeamAI 2-player mode: non-host will become lobby team leader');
    }
};



const originalTeamLeader = ref(null);

// Modified applyGameSettings function
const applyGameSettings = async (settings) => {
    console.log('Applying enhanced game settings:', settings);
    
    // Update local settings
    selectedDifficulty.value = settings.difficulty_id;
    selectedCategory.value = settings.category_id;
    playWithAI.value = settings.play_with_ai;
    playWithBespokeAI.value = settings.play_with_bespoke_ai;

    if (playWithAI.value || playWithBespokeAI.value) {
        playWithAISection.value = true;
    }
    
    // Apply existing team settings
    if (settings.join_team_with_players !== undefined) {
        joinTeamWithPlayers.value = settings.join_team_with_players;
    }
    if (settings.join_team_with_ai !== undefined) {
        joinTeamWithAI.value = settings.join_team_with_ai;
    }
    
    // Apply NEW team settings
    if (settings.lobby_team_leader !== undefined) {
        if (settings.is_lobby_team_leader && props.auth.user.name === settings.starter_name) {
            isLobbyTeamLeader.value = true;
            lobbyTeamLeader.value = props.auth.user.name;
        } else if (settings.lobby_team_leader) {
            lobbyTeamLeader.value = settings.starter_name;
        }
    }
    
    if (settings.selected_team !== undefined) {
        selectedTeam.value = settings.selected_team;
    }
    
    if (settings.is_team1_leader && props.auth.user.name === settings.starter_name) {
        isTeamLeader.value = true;
        team1Leader.value = props.auth.user.name;
    } else if (settings.is_team2_leader && props.auth.user.name === settings.starter_name) {
        isTeamLeader.value = true;
        team2Leader.value = props.auth.user.name;
    }
    
    // Set team mode
    isTeamMode.value = joinTeamWithPlayers.value || joinTeamWithAI.value || 
                       shouldShowLobbyTeamLeader.value || shouldShowTeamSelection.value;
    
    // Handle team leadership for existing modes
    if (joinTeamWithPlayers.value && playerCount.value > 1) {
        if (!originalTeamLeader.value) {
            originalTeamLeader.value = settings.starter_name;
        }
        
        const trueTeamLeaderName = originalTeamLeader.value;
        
        if (props.auth.user.name === trueTeamLeaderName) {
            isTeamLeader.value = true;
            teamLeaderName.value = props.auth.user.name;
        } else {
            isTeamLeader.value = false;
            teamLeaderName.value = trueTeamLeaderName;
        }
    }
    
    if (joinTeamWithAI.value && playerCount.value > 1) {
        if (playerCount.value === 2) {
            // 2-player teamAI: non-host becomes lobby team leader
            if (props.auth.user.name !== settings.starter_name) {
                isLobbyTeamLeader.value = true;
                lobbyTeamLeader.value = props.auth.user.name;
            } else {
                isTeamLeader.value = true;
                teamLeaderName.value = props.auth.user.name;
            }
        } else {
            // 3+ player teamAI: use lobby team leader system
            if (props.auth.user.name === settings.starter_name) {
                isTeamLeader.value = true;
                teamLeaderName.value = props.auth.user.name;
            }
        }
    }
    
    if (settings.selected_ai_model) {
        selectedAIModel.value = settings.selected_ai_model;
    }
    
    if (settings.questions && settings.questions.length > 0) {
        currentGameQuestions.value = settings.questions;
        console.log('Questions updated from game settings:', settings.questions.length, 'questions');
    } else {
        await fetchGameQuestions(settings.difficulty_id, settings.category_id);
    }

    let message = `Game settings updated to match ${originalTeamLeader.value || settings.starter_name}'s preferences!`;
    
    if (shouldShowLobbyTeamLeader.value) {
        message += ` Lobby team leader mode activated.`;
    } else if (shouldShowTeamSelection.value) {
        message += ` Team selection mode activated.`;
    } else if (isTeamMode.value) {
        if (joinTeamWithPlayers.value) {
            message += ` Team Player mode activated.`;
        } else if (joinTeamWithAI.value) {
            message += ` Team AI mode activated.`;
        }
    }
    
    addFlashMessage(message, 'info');
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


// Add these computed properties:

const shouldShowTeamSelection = computed(() => {
    return playerCount.value >= 3 && 
           !joinTeamWithPlayers.value && 
           !joinTeamWithAI.value && 
           !playWithAI.value && 
           !playWithBespokeAI.value;
});

const shouldShowLobbyTeamLeader = computed(() => {
    return playerCount.value >= 2 && 
           !joinTeamWithPlayers.value && 
           !joinTeamWithAI.value && 
           (playWithAI.value || playWithBespokeAI.value);
});


// Add team mode computed properties
const canSubmitAnswers = computed(() => {
    if (!isGameStarted.value) {
        return false;
    }

    if (playerCount.value === 1) return true;
    
    // Lobby team leader mode (2+ players with AI, no team modes)
    if (shouldShowLobbyTeamLeader.value) {
        return isLobbyTeamLeader.value;
    }
    
    // 3+ players without AI - team selection mode
    if (shouldShowTeamSelection.value) {
        return isTeam1Leader.value || isTeam2Leader.value || isTeam1Leader.value; // Fixed to use computed properties
    }
    
    // TeamAI game mode
    if (joinTeamWithAI.value) {
        if (playerCount.value === 2) {
            return !isTeamLeader.value; // Non-host is the lobby team leader
        } else {
            return isLobbyTeamLeader.value;
        }
    }
    
    // Team player mode
    if (joinTeamWithPlayers.value) {
        return isTeamLeader.value;
    }
    
    // Default multiplayer without team modes
    return true;
});

const shouldShowSuggestionInput = computed(() => {
    if (playerCount.value === 1) return false;
    
    const duringGame = isGameStarted.value && !gameIsOver.value;
    const afterTeamPlayerGame = gameIsOver.value && joinTeamWithPlayers.value;
    
    // Lobby team leader mode
    if (shouldShowLobbyTeamLeader.value) {
        return duringGame || afterTeamPlayerGame;
    }
    
    // Team selection mode (3+ players without AI)
    if (shouldShowTeamSelection.value) {
        return duringGame || afterTeamPlayerGame;
    }
    
    // TeamAI game
    if (joinTeamWithAI.value) {
        if (playerCount.value === 2) {
            return duringGame && isTeamLeader.value; // Only host can send suggestions
        } else {
            return duringGame; // All can send suggestions
        }
    }
    
    // Existing team modes
    if (!isTeamMode.value) return false;
    if (joinTeamWithPlayers.value && isTeamLeader.value) return false;
    
    return duringGame || afterTeamPlayerGame;
});

const getSuggestionPlaceholder = () => {
    if (shouldShowLobbyTeamLeader.value) {
        return isLobbyTeamLeader.value ? 
            'Send suggestion to team members...' : 
            'Send suggestion to team leader...';
    }
    
    if (shouldShowTeamSelection.value) {
        if (isTeam1Leader.value) {
            return 'Send suggestion to Team 1 members...';
        } else if (isTeam2Leader.value) {
            return 'Send suggestion to Team 2 members...';
        } else {
            const teamNum = selectedTeam.value;
            return `Send suggestion to Team ${teamNum} leader...`;
        }
    }
    
    if (joinTeamWithAI.value && playerCount.value === 2) {
        return isTeamLeader.value ? 
            'Send suggestion to other player...' : 
            'Send suggestion to host...';
    }
    
    if (joinTeamWithPlayers.value) {
        return isTeamLeader.value ? 
            'Send suggestion to team members...' : 
            'Send suggestion to team leader...';
    } else if (joinTeamWithAI.value) {
        return isTeamLeader.value ? 
            'Send suggestion to other players...' : 
            'Send suggestion to other players...';
    }
    return 'Send suggestion...';
};
const getSuggestionButtonText = () => {
    if (shouldShowLobbyTeamLeader.value) {
        return isLobbyTeamLeader.value ? 'Send To Team' : 'Send To Leader';
    }
    
    if (shouldShowTeamSelection.value) {
        if (isTeamLeader.value) {
            return `Send To Team ${selectedTeam.value}`;
        } else {
            return `Send To Leader`;
        }
    }
    
    if (joinTeamWithAI.value && playerCount.value === 2) {
        return isTeamLeader.value ? 'Send To Player' : 'Send To Host';
    }
    
    // Existing logic
    if (joinTeamWithPlayers.value) {
        return isTeamLeader.value ? 'Send To Team' : 'Send To Leader';
    } else if (joinTeamWithAI.value) {
        return 'Send To Others';
    }
    return 'Send Suggestion';
};

const selectTeam = async (teamNumber) => {
    const oldTeam = selectedTeam.value;
    selectedTeam.value = teamNumber;
    
    // Update local team assignment tracking
    playerTeamAssignments.value.set(props.auth.user.id, teamNumber);
    
    // Update team leadership status if this player was already a leader
    if (oldTeam === 1 && team1Leader.value === props.auth.user.name && teamNumber !== 1) {
        // Player was Team 1 leader but switched teams - remove leadership
        team1Leader.value = null;
        isTeamLeader.value = false;
    } else if (oldTeam === 2 && team2Leader.value === props.auth.user.name && teamNumber !== 2) {
        // Player was Team 2 leader but switched teams - remove leadership
        team2Leader.value = null;
        isTeamLeader.value = false;
    } else if (teamNumber === 1 && team1Leader.value === props.auth.user.name) {
        isTeamLeader.value = true;
    } else if (teamNumber === 2 && team2Leader.value === props.auth.user.name) {
        isTeamLeader.value = true;
    }
    
    // Update team player lists immediately for current player
    updateTeamPlayerLists();
    
    // Broadcast team selection with enhanced data
    try {
        await axios.post(`/api/games/${props.gameId}/broadcast`, {
            event: 'player.team.selected',
            data: {
                userId: props.auth.user.id,
                userName: props.auth.user.name,
                teamNumber: teamNumber,
                oldTeamNumber: oldTeam,
                // Include leadership changes
                wasTeam1Leader: oldTeam === 1 && team1Leader.value === null,
                wasTeam2Leader: oldTeam === 2 && team2Leader.value === null,
                timestamp: new Date().toISOString()
            }
        });
        
        addFlashMessage(`You selected Team ${teamNumber}`, 'info');
    } catch (error) {
        console.error('Failed to broadcast team selection:', error);
    }
};


const selectLobbyTeamLeader = async () => {
    isLobbyTeamLeader.value = true;
    lobbyTeamLeader.value = props.auth.user.name;
    
    // Broadcast lobby team leader selection
    try {
        await axios.post(`/api/games/${props.gameId}/broadcast`, {
            event: 'lobby.team.leader.selected',
            data: {
                userId: props.auth.user.id,
                userName: props.auth.user.name,
                timestamp: new Date().toISOString()
            }
        });
        
        addFlashMessage('You are now the lobby team leader!', 'success');
    } catch (error) {
        console.error('Failed to broadcast lobby team leader selection:', error);
    }
};

const selectTeamLeader = async (teamNumber) => {
    const currentUserName = props.auth.user.name;
    const isCurrentlyLeader = (teamNumber === 1 && team1Leader.value === currentUserName) ||
                             (teamNumber === 2 && team2Leader.value === currentUserName);
    
    if (isCurrentlyLeader) {
        // Unselect as team leader
        if (teamNumber === 1) {
            team1Leader.value = null;
        } else {
            team2Leader.value = null;
        }
        isTeamLeader.value = false;
        
        // Broadcast team leader unselection
        try {
            await axios.post(`/api/games/${props.gameId}/broadcast`, {
                event: 'team.leader.unselected',
                data: {
                    userId: props.auth.user.id,
                    userName: props.auth.user.name,
                    teamNumber: teamNumber,
                    timestamp: new Date().toISOString()
                }
            });
            
            addFlashMessage(`You are no longer Team ${teamNumber} leader`, 'info');
        } catch (error) {
            console.error('Failed to broadcast team leader unselection:', error);
        }
    } else {
        // Select as team leader
        if (teamNumber === 1) {
            // Remove from Team 2 leadership if applicable
            if (team2Leader.value === currentUserName) {
                team2Leader.value = null;
            }
            team1Leader.value = currentUserName;
        } else {
            // Remove from Team 1 leadership if applicable
            if (team1Leader.value === currentUserName) {
                team1Leader.value = null;
            }
            team2Leader.value = currentUserName;
        }
        
        // Only set isTeamLeader if this player is actually on the team they're leading
        if (selectedTeam.value === teamNumber) {
            isTeamLeader.value = true;
        }
        
        // Broadcast team leader selection
        try {
            await axios.post(`/api/games/${props.gameId}/broadcast`, {
                event: 'team.leader.selected',
                data: {
                    userId: props.auth.user.id,
                    userName: props.auth.user.name,
                    teamNumber: teamNumber,
                    // Include if they were previously leader of other team
                    previousTeam1Leader: teamNumber === 2 && team1Leader.value === null,
                    previousTeam2Leader: teamNumber === 1 && team2Leader.value === null,
                    timestamp: new Date().toISOString()
                }
            });
            
            addFlashMessage(`You are now Team ${teamNumber} leader!`, 'success');
        } catch (error) {
            console.error('Failed to broadcast team leader selection:', error);
        }
    }
};

const toggleAICheckboxes = async () => {

    if (playWithAISection.value) {
        playWithBespokeAI.value = false;
        playWithAI.value = false;
        joinTeamWithPlayers.value = false;
        joinTeamWithAI.value = false;
    } else {
        playWithBespokeAI.value = true;
    }

    updateGameSettings();
}

const onTeamPlayerSettingChange = async () => {
    if (!isInGame.value || playerCount.value === 1) return;

    if (joinTeamWithPlayers.value && joinTeamWithAI.value) {
        joinTeamWithAI.value = false;
    }

    if (!playWithAISection.value) {
        playWithAISection.value = true;
        playWithBespokeAI.value = true;
    }
    
    // Update team mode status
    isTeamMode.value = joinTeamWithPlayers.value || joinTeamWithAI.value;

    updateGameSettings();
    
};

const onTeamAISettingChange = async () => {
    if (!isInGame.value || playerCount.value === 1) return;

    if (joinTeamWithPlayers.value) {
        if (joinTeamWithAI.value) {
            joinTeamWithPlayers.value = false;
        }
    }

    if (!playWithAISection.value) {
        playWithAISection.value = true;
        playWithBespokeAI.value = true;
    }

    
    // Update team mode status
    isTeamMode.value = joinTeamWithPlayers.value || joinTeamWithAI.value;

    updateGameSettings();
    
};

const updateGameSettings = async () => {
    if (!playWithAI.value && !playWithBespokeAI.value) {
        playWithAISection.value = false;
    }

    try {
        await axios.post(`/api/games/${props.gameId}/broadcast`, {
            event: 'team.settings.changed',
            data: {
                userId: props.auth.user.id,
                userName: props.auth.user.name,
                joinTeamWithPlayers: joinTeamWithPlayers.value,
                joinTeamWithAI: joinTeamWithAI.value,
                gameSettings: {
                    difficulty_id: selectedDifficulty.value,
                    category_id: selectedCategory.value,
                    play_with_ai: playWithAI.value,
                    play_with_bespoke_ai: playWithBespokeAI.value,
                    selected_ai_model: selectedAIModel.value,
                    join_team_with_players: joinTeamWithPlayers.value,
                    join_team_with_ai: joinTeamWithAI.value,
                    starter_name: props.auth.user.name,
                    questions: currentGameQuestions.value,
                    // NEW: Enhanced team settings
                    lobby_team_leader: shouldShowLobbyTeamLeader.value,
                    is_lobby_team_leader: isLobbyTeamLeader.value,
                    selected_team: selectedTeam.value,
                    is_team1_leader: isTeam1Leader.value,
                    is_team2_leader: isTeam2Leader.value
                },
                timestamp: new Date().toISOString()
            }
        });
    } catch (error) {
        console.error('Failed to broadcast team setting change:', error);
    }
};

const sendSuggestion = async () => {
    if (!suggestionInput.value.trim()) return;
    
    try {
        let eventData = {
            userId: props.auth.user.id,
            userName: props.auth.user.name,
            suggestion: suggestionInput.value.trim(),
            timestamp: new Date().toISOString()
        };
        
        // Lobby team leader mode
        if (shouldShowLobbyTeamLeader.value) {
            eventData.gameMode = 'lobbyTeamLeader';
            eventData.isLobbyTeamLeader = isLobbyTeamLeader.value;
            eventData.isToLeader = !isLobbyTeamLeader.value;
        }
        // Team selection mode
        else if (shouldShowTeamSelection.value) {
            eventData.gameMode = 'teamSelection';
            eventData.senderTeam = selectedTeam.value;
            eventData.isTeamLeader = isTeamLeader.value;
            eventData.isToLeader = !isTeamLeader.value;
        }
        // TeamAI 2-player mode
        else if (joinTeamWithAI.value && playerCount.value === 2) {
            eventData.gameMode = 'teamAI2Player';
            eventData.isHost = isTeamLeader.value;
            eventData.isToHost = !isTeamLeader.value;
        }
        // Existing team modes
        else {
            eventData.gameMode = joinTeamWithPlayers.value ? 'teamPlayer' : 'teamAI';
            eventData.isToLeader = joinTeamWithPlayers.value && !isTeamLeader.value;
            eventData.isToOtherPlayers = joinTeamWithAI.value && !isTeamLeader.value;
            eventData.isTeamLeader = isTeamLeader.value;
        }
        
        await axios.post(`/api/games/${props.gameId}/broadcast`, {
            event: 'team.suggestion',
            data: eventData
        });
        
        addFlashMessage(`Suggestion sent: "${suggestionInput.value.trim()}"`, 'info');
        suggestionInput.value = '';
    } catch (error) {
        console.error('Failed to send suggestion:', error);
        addFlashMessage('Failed to send suggestion', 'error');
    }
};


// Handle player count changes - FIXED to ensure numeric value
const onPlayerCountChange = async (newCount) => {
    const numericCount = parseInt(newCount);
    const oldCount = playerCount.value;
    playerCount.value = numericCount;
    
    // Reset team states when player count changes
    if (oldCount !== numericCount) {
        // Reset team selections when switching modes
        selectedTeam.value = 1;
        team1Leader.value = null;
        team2Leader.value = null;
        lobbyTeamLeader.value = null;
        isLobbyTeamLeader.value = false;
        isTeamLeader.value = false;
        isTeamMode.value = false;
        
        // Reset team mode checkboxes when going to/from single player
        if (numericCount === 1) {
            joinTeamWithPlayers.value = false;
            joinTeamWithAI.value = false;
        }
        
    }
    
    if (isInGame.value) {
        try {
            await changePlayerCount(numericCount);
        } catch (error) {
            console.error('Failed to sync player count:', error);
        }
    }
};

const updateTeamPlayerLists = () => {
    console.log('Updating team player lists with current assignments...');
    
    // Clear existing team assignments
    team1Players.value = [];
    team2Players.value = [];
    
    // Assign players based on their actual team selections
    players.value.forEach(player => {
        console.log('PLAYER: ' + player.id);
        const assignedTeam = playerTeamAssignments.value.get(player.id);
        console.log('assignedTeam: ' + JSON.stringify(assignedTeam));
        if (assignedTeam === 1) {
            team1Players.value.push(player);
        } else if (assignedTeam === 2) {
            team2Players.value.push(player);
        } else {
            // For players who haven't selected a team yet, assign to Team 1 by default
            // but don't broadcast this - let them choose manually
            if (player.id === props.auth.user.id) {
                // Current user hasn't selected yet - don't auto-assign
                console.log('Current user has not selected a team yet');
            }
        }
    });
    
    console.log('Team assignments updated:', {
        team1: team1Players.value.map(p => p.name),
        team2: team2Players.value.map(p => p.name),
        assignments: Object.fromEntries(playerTeamAssignments.value)
    });
};

const nextOrSubmit = async () => {
    // Check if player can submit answers (team mode restriction)
    if (!canSubmitAnswers.value) {
        let leaderName = '';
        if (shouldShowLobbyTeamLeader.value) {
            leaderName = lobbyTeamLeader.value;
        } else if (shouldShowTeamSelection.value) {
            leaderName = selectedTeam.value === 1 ? team1Leader.value : team2Leader.value;
        } else {
            leaderName = teamLeaderName.value;
        }
        
        addFlashMessage(`Only the team leader (${leaderName}) can submit answers.`, 'warning');
        return;
    }

    if (!isLastQuestion.value) {
        // ENHANCED: Use the new answerQuestion function with auto-progression
        const result = await answerQuestion(currentQuestionIndex.value, answers.value[currentQuestionIndex.value], playerCount.value, isTeamLeader.value);
        
        if (result.submitted || joinTeamWithPlayers.value) {

            // Answer was submitted successfully (single player or all players answered)
            // Check if we're playing with AI and need to wait for AI answer
            if ((playWithAI.value || playWithBespokeAI.value) && currentGameQuestions.value[currentQuestionIndex.value]) {
                // For single player: Get AI answer immediately
                if (playerCount.value === 1) {
                    console.log('Single player: Requesting AI answers for question:', currentQuestionIndex.value);
                    
                    // Get ChatGPT AI answer if enabled
                    if (playWithAI.value) {
                        await getAIAnswerForQuestion(
                            currentGameQuestions.value[currentQuestionIndex.value].question,
                            props.gameId,
                            currentQuestionIndex.value,
                            selectedDifficulty.value,
                            selectedCategory.value
                        );
                    }
                    
                    // Get Bespoke AI answer if enabled
                    if (playWithBespokeAI.value) {
                        await getBespokeAIAnswerForQuestion(
                            currentGameQuestions.value[currentQuestionIndex.value].question,
                            props.gameId,
                            currentQuestionIndex.value,
                            selectedDifficulty.value,
                            selectedCategory.value
                        );
                    }
                    
                    // Progress to next question immediately after AI responds
                    currentQuestionIndex.value++;
                } else {
                    // For multiplayer: Only the first player to trigger "all answered" should request AI
                    console.log('Multiplayer: All players answered, checking if need to request AI');
                    
                    let shouldRequestAI = false;
                    
                    // Check if either AI hasn't answered this question yet
                    if (playWithAI.value && !hasAIAnswered(currentQuestionIndex.value)) {
                        shouldRequestAI = true;
                    }
                    if (playWithBespokeAI.value && !hasBespokeAIAnswered(currentQuestionIndex.value)) {
                        shouldRequestAI = true;
                    }
                    
                    if (shouldRequestAI) {
                        console.log('Requesting AI answers for question:', currentQuestionIndex.value);
                        
                        // Get ChatGPT AI answer if enabled and not answered
                        if (playWithAI.value && !hasAIAnswered(currentQuestionIndex.value)) {
                            await getAIAnswerForQuestion(
                                currentGameQuestions.value[currentQuestionIndex.value].question,
                                props.gameId,
                                currentQuestionIndex.value,
                                selectedDifficulty.value,
                                selectedCategory.value
                            );
                        }
                        
                        // Get Bespoke AI answer if enabled and not answered
                        if (playWithBespokeAI.value && !hasBespokeAIAnswered(currentQuestionIndex.value)) {
                            await getBespokeAIAnswerForQuestion(
                                currentGameQuestions.value[currentQuestionIndex.value].question,
                                props.gameId,
                                currentQuestionIndex.value,
                                selectedDifficulty.value,
                                selectedCategory.value
                            );
                        }
                        
                        // Broadcast that AI has answered and all players can progress
                        await axios.post(`/api/games/${props.gameId}/broadcast`, {
                            event: 'ai.answered',
                            data: {
                                questionIndex: currentQuestionIndex.value,
                                // ChatGPT AI data
                                aiAnswer: aiAnswers.value[currentQuestionIndex.value]?.answer,
                                aiScore: aiAnswers.value[currentQuestionIndex.value]?.score,
                                isCorrect: aiAnswers.value[currentQuestionIndex.value]?.isCorrect,
                                steal: aiAnswers.value[currentQuestionIndex.value]?.steal,
                                aiData: aiAnswers.value[currentQuestionIndex.value],
                                // Bespoke AI data
                                bespokeAIAnswer: bespokeAIAnswers.value[currentQuestionIndex.value]?.answer,
                                bespokeAIScore: bespokeAIAnswers.value[currentQuestionIndex.value]?.score,
                                bespokeAIPredictedScore: bespokeAIAnswers.value[currentQuestionIndex.value]?.predicted_score,
                                bespokeAIIsCorrect: bespokeAIAnswers.value[currentQuestionIndex.value]?.isCorrect,
                                bespokeAISteal: bespokeAIAnswers.value[currentQuestionIndex.value]?.steal,
                                bespokeAIModelId: bespokeAIAnswers.value[currentQuestionIndex.value]?.model_id,
                                bespokeAIData: bespokeAIAnswers.value[currentQuestionIndex.value],
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
        // This is the LAST question - use existing final submission logic with Bespoke AI support
        submitting.value = true;

        try {
            // For single-player: Get AI answer for final question if needed
            if (playerCount.value === 1) {
                if (playWithAI.value && !hasAIAnswered(currentQuestionIndex.value)) {
                    addFlashMessage('Getting ChatGPT AI answer for final question...', 'info');
                    console.log('Single-player: Getting ChatGPT AI answer for final question:', currentQuestionIndex.value);
                    await getAIAnswerForQuestion(
                        currentGameQuestions.value[currentQuestionIndex.value].question,
                        props.gameId,
                        currentQuestionIndex.value,
                        selectedDifficulty.value,
                        selectedCategory.value
                    );
                }
                
                if (playWithBespokeAI.value && !hasBespokeAIAnswered(currentQuestionIndex.value)) {
                    addFlashMessage('Getting Bespoke AI answer for final question...', 'info');
                    console.log('Single-player: Getting Bespoke AI answer for final question:', currentQuestionIndex.value);
                    await getBespokeAIAnswerForQuestion(
                        currentGameQuestions.value[currentQuestionIndex.value].question,
                        props.gameId,
                        currentQuestionIndex.value,
                        selectedDifficulty.value,
                        selectedCategory.value
                    );
                }
            }
            
            // For multiplayer: Check if AI needs to answer (only team leader can trigger this)
            if (playerCount.value > 1 && (isTeamLeader.value || joinTeamWithAI.value)) {
                if (playWithAI.value && !hasAIAnswered(currentQuestionIndex.value)) {
                    addFlashMessage('Waiting for ChatGPT AI to answer the final question...', 'info');
                    console.log('Multiplayer: Triggering ChatGPT AI answer for final question:', currentQuestionIndex.value);
                    await getAIAnswerForQuestion(
                        currentGameQuestions.value[currentQuestionIndex.value].question,
                        props.gameId,
                        currentQuestionIndex.value,
                        selectedDifficulty.value,
                        selectedCategory.value
                    );
                }
                
                if (playWithBespokeAI.value && !hasBespokeAIAnswered(currentQuestionIndex.value)) {
                    addFlashMessage('Waiting for Bespoke AI to answer the final question...', 'info');
                    console.log('Multiplayer: Triggering Bespoke AI answer for final question:', currentQuestionIndex.value);
                    await getBespokeAIAnswerForQuestion(
                        currentGameQuestions.value[currentQuestionIndex.value].question,
                        props.gameId,
                        currentQuestionIndex.value,
                        selectedDifficulty.value,
                        selectedCategory.value
                    );
                }
            }

            console.log('LOGLOGLOGLOGLOGLOGLOGLOGLOGLOG - NOS FINAL Q');

            // TEAM PLAYER GAME FIX: Non-leaders need to submit at the last question
            // but with empty answers since they'll get team leader's answers in the controller
            let submissionAnswers = answers.value.map(answer => answer);
            
            // For team player game non-leaders, send empty answers array
            // The controller will replace with team leader's cached answers
            if (joinTeamWithPlayers.value && !isTeamLeader.value) {
                console.log('Team player game non-leader: Submitting with empty answers for controller replacement');
                submissionAnswers = new Array(currentGameQuestions.value.length).fill('');
            }

            const result = await submitAnswers(
                submissionAnswers, 
                playerCount.value, 
                selectedDifficulty.value, 
                selectedCategory.value,
                isTeamLeader.value,
                // NEW PARAMETERS:
                {
                    lobbyTeamLeader: shouldShowLobbyTeamLeader.value,
                    isLobbyTeamLeader: isLobbyTeamLeader.value,
                    selectedTeam: selectedTeam.value,
                    isTeam1Leader: isTeam1Leader.value,
                    isTeam2Leader: isTeam2Leader.value
                }
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
                                playedWithAI: playWithAI.value,
                                playedWithBespokeAI: playWithBespokeAI.value,
                                teamSettings: {
                                    joinTeamWithPlayers: joinTeamWithPlayers.value,
                                    joinTeamWithAI: joinTeamWithAI.value
                                }
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

const debugBespokeAIAnswers = computed(() => {
    const debug = {};
    Object.keys(bespokeAIAnswers.value).forEach(key => {
        debug[key] = {
            hasAnswer: !!bespokeAIAnswers.value[key]?.answer,
            hasScore: bespokeAIAnswers.value[key]?.score !== undefined,
            score: bespokeAIAnswers.value[key]?.score
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
    console.log('Enhanced reset - clearing all team states');
    
    // Reset question index and answers
    currentQuestionIndex.value = 0;
    answers.value = [];
    
    // Reset game state flags
    isGameStarted.value = false;
    gameIsOver.value = false;
    isWaitingToStart.value = false;
    preReadyPlayers.value.clear();
    gameState.value.waitingForOthers = false;
    gameState.value.playersReady.clear();
    
    // Reset ALL team settings
    isTeamMode.value = false;
    isTeamLeader.value = false;
    teamLeaderName.value = '';
    suggestionInput.value = '';
    
    // Reset NEW team states
    selectedTeam.value = 1;
    team1Leader.value = null;
    team2Leader.value = null;
    team1Players.value = []; // Now properly defined
    team2Players.value = []; // Now properly defined
    lobbyTeamLeader.value = null;
    isLobbyTeamLeader.value = false;
    
    // Reset team mode flags
    joinTeamWithPlayers.value = false;
    joinTeamWithAI.value = false;
    
    hasNonLeaderSubmitted.value = false;
    originalTeamLeader.value = null;
    
    console.log('Enhanced game state fully reset');
};

// Vertical Nav Section:
const navSections = [
    { id: 'main', name: 'Main' },
    { id: 'scores', name: 'Scores' },
    { id: 'stats', name: 'Stats' },
];

const toggleGameFilters = () => {
  // Emit an event that the GameAuthenticatedLayout can listen for
  window.dispatchEvent(new CustomEvent('toggleGameFilters'));
};

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
    showVerticalNav.value = scrollY > windowHeight * 0.1;

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

const getDifficultyName = (difficultyId) => {
    const difficulty = props.difficulties.find(d => d.id === difficultyId);
    return difficulty ? difficulty.name : 'Unknown';
};

const getCategoryName = (categoryId) => {
    const category = props.categories.find(c => c.id === categoryId);
    return category ? category.name : 'Unknown';
};

// Player & AI Scores Tables Filtering:
const filteredAndSortedGameScores = computed(() => {
    let filtered = gameScores.value;
    
    if (playerSearchQuery.value.trim()) {
        const query = playerSearchQuery.value.toLowerCase().trim();
        filtered = filtered.filter(score => 
            score.session_id.toString().includes(query)
        );
    }
    
    return filtered;
});

const filteredAndSortedAIScores = computed(() => {
    let filtered = aiScores.value;
    
    if (aiSearchQuery.value.trim()) {
        const query = aiSearchQuery.value.toLowerCase().trim();
        filtered = filtered.filter(score => 
            score.session_id.toString().includes(query)
        );
    }
    
    return filtered;
});

const sortPlayerTable = (field) => {
    if (playerSortField.value === field) {
        playerSortDirection.value = playerSortDirection.value === 'desc' ? 'asc' : 'desc';
    } else {
        playerSortField.value = field;
        playerSortDirection.value = 'desc';
    }

    fetchGameScores(1, playerSortField.value, playerSortDirection.value);
};

const sortAITable = (field) => {
    if (aiSortField.value === field) {
        aiSortDirection.value = aiSortDirection.value === 'desc' ? 'asc' : 'desc';
    } else {
        aiSortField.value = field;
        aiSortDirection.value = 'desc';
    }

    fetchAIScores(1, aiSortField.value, aiSortDirection.value);
};

const calculatePercentage = (score) => {
    const maxScore = score.answer_json?.max_score ? parseInt(score.answer_json.max_score) : 10;
    return ((score.score / maxScore) * 100).toFixed(1);
};

const appliedFilters = ref({
    dateRange: [null, null],
    userIds: [],
    andUsers: false,
    excludeAI: true,
    difficultyId: null,
    categoryId: null,
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

    appliedFilters.value.difficultyId = urlParams.get('difficulty');

    appliedFilters.value.categoryId = urlParams.get('category');
};

const stealAnimation = async () => {
    // Set the current answer to 'STEAL'
    answers.value[currentQuestionIndex.value] = 'STEAL';
    
    // Trigger the animation
    isStealAnimating.value = true;
    
    // Wait 2 seconds for animation, then proceed
    setTimeout(async () => {
        isStealAnimating.value = false;
        
        // Automatically proceed to next question or submit
        await nextOrSubmit();
    }, 2000);
};

const handleFilterChange = (event) => {
    appliedFilters.value = {
        dateRange: event.detail.dateRange || [null, null],
        userIds: event.detail.userIds || [],
        andUsers: event.detail.andUsers || false,
        excludeAI: event.detail.excludeAI !== undefined ? event.detail.excludeAI : true,
        difficultyId: event.detail.difficultyId || null,
        categoryId: event.detail.categoryId || null
    };
    
    // Refresh scores when filters change (maintaining current sort)
    fetchGameScores(1, playerSortField.value, playerSortDirection.value);
    fetchAIScores(1, aiSortField.value, aiSortDirection.value);
};

watch(() => gameState.value.gameInProgress, (newVal, oldVal) => {
    if (newVal && !oldVal) {
        console.log('Game started - AI answers preserved:', Object.keys(aiAnswers.value));
        console.log('Game started - Bespoke AI answers preserved:', Object.keys(bespokeAIAnswers.value));
    }
});

// Lifecycle: fetch data
onMounted( () => {
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
        onUpdateTeamLists: updateTeamPlayerLists,
        onChartsUpdate: async () => {
            console.log('🔄 Refreshing charts...');
            if (gameGraphRef.value?.refreshChart) {
                await gameGraphRef.value.refreshChart();
            }
            if (gameHeatmapRef.value?.refreshHeatmap) {
                await gameHeatmapRef.value.refreshHeatmap();
            }
        },
        onUpdateTeamAssignments: (userId, teamNumber) => {
            playerTeamAssignments.value.set(userId, teamNumber);
            updateTeamPlayerLists(); // Refresh the lists after assignment
        },
        onGameComplete: async (data) => {
            console.log('🔄 Game completed callback triggered');
            
            // For joinTeamWithPlayers games, non-leaders need to submit to hit controller
            if (data.teamPlayerGame && !isTeamLeader.value && !hasNonLeaderSubmitted.value) {
                console.log('Non-team leader submitting to controller to retrieve team answers');
                hasNonLeaderSubmitted.value = true; // Prevent duplicate submissions
                
                try {
                    // Submit empty answers - controller will replace with team leader's cached answers
                    const emptyAnswers = new Array(currentGameQuestions.value.length).fill('');
                    
                    // Call the controller directly without going through the normal submitAnswers flow
                    const response = await axios.post(`/games/${props.gameId}/submit-answer`, {
                        answers: emptyAnswers,
                        difficulty_id: selectedDifficulty.value,
                        category_id: selectedCategory.value,
                        teamPlayerGame: true,
                        isTeamLeader: false,
                        // Include AI answers if they were enabled
                        ...(playWithAI.value && {
                            aiAnswers: new Array(currentGameQuestions.value.length).fill(''),
                            playWithAI: true
                        }),
                        ...(playWithBespokeAI.value && {
                            bespokeAIAnswers: new Array(currentGameQuestions.value.length).fill(''),
                            playWithBespokeAI: true,
                            selectedAIModel: selectedAIModel.value
                        })
                    });
                    
                    console.log('Non-team leader submission successful:', response.data);
                } catch (error) {
                    console.error('Non-team leader submission failed:', error);
                    addFlashMessage('Failed to sync with team answers', 'error');
                }
            }
            
            // Reset main component state for all players
            gameIsOver.value = true;
            isGameStarted.value = false;
            gameState.value.waitingForOthers = false;
            
            // Call the main component's resetGameState
            resetGameState();
            
            addFlashMessage('Game completed! Team answers have been submitted.', 'success');
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

                    if (((playWithAI.value && !hasAIAnswered(questionIndex)) || (playWithBespokeAI.value && !hasBespokeAIAnswered(questionIndex))) && !joinTeamWithPlayers.value) {
                        console.log('All players answered, waiting for AI...');
                        addFlashMessage('All players answered! Waiting for AI...', 'info');
                    } else {
                        currentQuestionIndex.value++;
                        addFlashMessage('All players answered! Moving to next question!', 'success');
                    }
                }
            }
        },
        onAutoStart: async (isWaitingToStart) => {
            console.log('🚀 Auto-starting game via callback...');
            
            if (playerCount.value === 1 && !playWithAI.value && !playWithBespokeAI.value) {
                addFlashMessage('Cannot start game. Please enable "Play With ChatGPT" or "Play With Learning Model" or add more players.', 'warning');
                return;
            }
            console.log('IS WAITING' + isWaitingToStart);
            
            if (isWaitingToStart) {
                isGameStarted.value = true;
                gameIsOver.value = false;
                isWaitingToStart = false;
                preReadyPlayers.value.clear();
            } else {
                notInActiveGame.value = true;
            }
            
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
        <GameAuthenticatedLayout :currentGameId="props.gameId" :difficulties="props.difficulties" :categories="props.categories">


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

                <transition name="fade">
                    <div
                        v-if="currentNavSection !== 0 && showVerticalNav"
                        class="fixed left-2 top-1/2 transform -translate-y-1/2 translate-x-20 z-10 hidden sm:block"
                    >
                        <button 
                            @click="toggleGameFilters"
                            class="w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-700 transition-colors group"
                            title="Game Filters"
                        >
                            <svg class="w-5 h-5 text-white group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                        </button>
                    </div>
                </transition>
                        

            <div class="py-4 mb-6">
                <div class="w-min-[100px] sm:w-auto pl-6 main-width mx-auto sm:px-6 lg:px-8">
                    <div v-if="errorMessage" class="mb-4 p-4 bg-red-900 text-red-200 rounded border border-red-700">
                        {{ errorMessage }}
                    </div>

                    <div class="flex flex-wrap gap-6 justify-center items-start">


                        <div v-if="showQuestionInput" class="basis-full mb-6">
                            <div class="text-center mb-2 text-gray-400 text-sm font-medium">
                                Question {{ currentQuestionIndex + 1 }} / {{ currentGameQuestions.length }}
                            </div>
                            <div class="text-center mb-4 text-white text-xl font-semibold">
                                {{ currentGameQuestions[currentQuestionIndex]?.question }}
                            </div>

                            <!-- Enhanced Answer Input Section -->
                            <div v-if="canSubmitAnswers" class="flex flex-col sm:flex-row gap-4 justify-center mb-4">
                                <button 
                                    :disabled="submitting || isStealAnimating" 
                                    @click="stealAnimation"
                                    class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:opacity-50 transition-colors">
                                    Steal
                                </button>
                                
                                <div class="relative w-full sm:w-2/3">
                                    <input 
                                        v-model="answers[currentQuestionIndex]"
                                        :class="{
                                            'animate-pulse bg-white !text-white': isStealAnimating,
                                            'bg-gray-100 !text-white': !isStealAnimating,
                                            'opacity-50 cursor-not-allowed': !canSubmitAnswers
                                        }"
                                        :disabled="isStealAnimating || (!canSubmitAnswers)"
                                        class="px-4 py-2 rounded w-full !placeholder-white transition-all duration-500"
                                        :placeholder="
                                            isStealAnimating ? '' : 
                                            (!canSubmitAnswers) ? getDisabledInputPlaceholder() :
                                            'Your answer'
                                        " 
                                    />
                                </div>

                                <button 
                                    :disabled="submitting || isStealAnimating" 
                                    @click="nextOrSubmit"
                                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50 transition-colors">
                                    {{ 
                                        isLastQuestion ? 
                                            (submitting ? 'Submitting...' : 'Submit') : 
                                            'Next' 
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- TEAM PLAYER NON-LEADER GAME VIEW -->
                        <div v-else-if="isGameStarted && !gameIsOver && joinTeamWithPlayers && !isTeamLeader" class="basis-full mb-6">
                            <div class="text-center mb-2 text-gray-400 text-sm font-medium">
                                Question {{ currentQuestionIndex + 1 }} / {{ currentGameQuestions.length }}
                            </div>
                            <div class="text-center mb-4 text-white text-xl font-semibold">
                                {{ currentGameQuestions[currentQuestionIndex]?.question }}
                            </div>
                            
                            <!-- Team Mode Indicator for Non-Leaders -->
                            <div class="text-center mb-4">
                                <div class="inline-block px-4 py-2 bg-blue-900 text-blue-200 rounded-lg border border-blue-700">
                                    <div class="text-sm font-semibold">
                                        {{ teamLeaderName }} is your team leader vs AI - send suggestions to help
                                    </div>
                                </div>
                            </div>

                            <!-- View-only answer field for non-leaders -->
                            <div class="flex flex-col sm:flex-row gap-4 justify-center mb-4">
                                <div class="relative w-full sm:w-2/3">
                                    <input 
                                        :value="'Waiting for team leader to answer...'"
                                        disabled
                                        class="px-4 py-2 rounded w-full bg-gray-600 text-gray-300 cursor-not-allowed"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Team Suggestion Input (updated logic for different team modes) -->
                        <div v-if="shouldShowSuggestionInput" class="flex basis-full justify-center gap-4">
                            <div class="relative w-full sm:w-2/3">
                                <input 
                                    v-model="suggestionInput"
                                    class="px-4 py-2 rounded w-full bg-gray-600 text-white placeholder-gray-300"
                                    :placeholder="getSuggestionPlaceholder()"
                                />
                            </div>
                            <button 
                                @click="sendSuggestion"
                                :disabled="!suggestionInput.trim()"
                                class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 disabled:opacity-50 transition-colors">
                                {{ getSuggestionButtonText() }}
                            </button>
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

                        <!-- Game In Progress Indicator -->
                        <div v-if="notInActiveGame && !showQuestionInput" 
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
                        <div v-if="!showQuestionInput && !isGameStarted" class="basis-full flex flex-col gap-6 p-4 bg-gray-800 rounded shadow text-white">

                            <!-- Row 1: Game Name & Buttons -->
                            <div class="flex items-center justify-center flex-wrap gap-4 min-h-[32px]">
                                <h2 class="text-xl text-white font-semibold">
                                    Lobby {{ game.id }}: {{ gameType.name || 'Unknown Game' }}
                                </h2>

                                
                                <button @click="startGame"
                                    :disabled="!isInGame || (isGameInProgress && !gameIsOver) || isWaitingToStart || notInActiveGame"
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

                                <!-- Waiting menu for multiplayer start -->
                                <div v-if="!isWaitingToStart && !isGameStarted && gameState.playersReady.size > 0 && !gameIsOver && !gameState.waitingForOthers && !notInActiveGame" class="basis-full mb-6 text-center">
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
                                                    <span class="font-semibold">Play With Bespoke AI:</span> 
                                                    {{ gameState.gameSettings?.play_with_bespoke_ai ? 'Yes' : 'No' }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold">Play With ChatGPT:</span> 
                                                    {{ gameState.gameSettings?.play_with_ai ? 'Yes' : 'No' }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold">Host & Lobby X AI:</span> 
                                                    {{ gameState.gameSettings?.join_team_with_players ? 'Yes' : 'No' }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold">Host & AI X Lobby:</span> 
                                                    {{ gameState.gameSettings?.join_team_with_ai ? 'Yes' : 'No' }}
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

                            <!-- Row 2: Enhanced Settings with Team Management -->
                            <div class="flex flex-wrap justify-center items-center gap-6">
                                <!-- Number of Players -->
                                <div class="flex items-center gap-2">
                                    <label for="players">Number of Players:</label>
                                    <select id="players" :value="playerCount" @change="onPlayerCountChange($event.target.value)"
                                        :disabled="isGameInProgress || isWaitingForOthers || gameIsOver"
                                        class="border rounded px-2 py-1 bg-gray-700 text-white disabled:opacity-50">
                                        <option value="1">1 Player</option>
                                        <option value="2">2 Players</option>
                                        <option value="3">3 Players</option>
                                        <option value="4">4 Players</option>
                                        <option value="5">5 Players</option>
                                    </select>
                                </div>

                                <!-- Team Options (only show for multiplayer) -->
                                <div v-if="playerCount > 1" class="flex items-center gap-4">
                                    <label>Team Games:</label>
                                    <div class="flex items-center bg-gray-200/10 p-2 rounded-lg gap-2">
                                        <input type="checkbox" v-model="joinTeamWithPlayers" 
                                            :disabled="isGameInProgress || isWaitingForOthers || gameIsOver" 
                                            @change="onTeamPlayerSettingChange" />
                                        <label style="color: white !important">Host & Lobby</label>
                                        <label style="color: #fa887f !important">Vs AI</label>
                                    </div>
                                    <div class="flex items-center bg-gray-200/10 p-2 rounded-lg gap-2">
                                        <input type="checkbox" v-model="joinTeamWithAI" 
                                            :disabled="isGameInProgress || isWaitingForOthers || gameIsOver"
                                            @change="onTeamAISettingChange" />
                                        <label style="color: white !important">Host & AI</label>
                                        <label style="color: #fa887f !important">Vs Lobby</label>
                                    </div>
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

                                <!-- Play With AI Section Toggle -->
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" v-model="playWithAISection" @click="toggleAICheckboxes"
                                        :disabled="isGameInProgress || isWaitingForOthers || gameIsOver" />
                                    <label>Play With AI</label>
                                </div>
                            </div>

                            <!-- Team Selection for 3+ players without AI -->
                            <div v-if="shouldShowTeamSelection" class="mt-6 p-4 bg-gray-700 rounded-lg">
                                <h3 class="text-white font-semibold text-center mb-4">Choose Your Team</h3>
                                
                                <div class="flex gap-8 justify-center">
                                    <!-- Team 1 -->
                                    <div class="flex flex-col items-center gap-3 p-4 bg-gray-600 rounded border-2 min-w-[200px]" 
                                        :class="{ 'border-blue-500': selectedTeam === 1, 'border-gray-500': selectedTeam !== 1 }">
                                        <h4 class="text-white font-medium">Team 1</h4>
                                        
                                        <label class="flex items-center gap-2 text-white cursor-pointer">
                                            <input type="radio" 
                                                name="teamSelection" 
                                                :value="1" 
                                                v-model="selectedTeam"
                                                @change="selectTeam(1)"
                                                :disabled="isTeam1RadioDisabled" />
                                            Join Team 1
                                        </label>
                                        
                                        <div class="text-center">
                                            <div class="text-sm text-gray-300 mb-2">Team Leader:</div>
                                            <div v-if="team1Leader" class="text-green-400 font-medium mb-2">{{ team1Leader }}</div>
                                            <div v-else class="text-yellow-400 text-xs mb-2">No leader selected</div>
                                            
                                            <!-- Become/Unselect Team 1 Leader Button -->
                                            <button v-if="canBecomeTeam1Leader" 
                                                    @click="selectTeamLeader(1)"
                                                    class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                                Become Team 1 Leader
                                            </button>
                                            
                                            <button v-if="canUnselectTeam1Leader" 
                                                    @click="selectTeamLeader(1)"
                                                    class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                                                Step Down as Leader
                                            </button>
                                        </div>
                                        
                                        <div class="text-center">
                                            <div class="text-sm text-gray-300 mb-1">Players:</div>
                                            <div class="text-xs text-gray-400 max-h-24 overflow-y-auto">
                                                <div v-for="player in team1Players" :key="player.id" 
                                                    :class="{ 'text-green-400 font-semibold': player.name === team1Leader }"
                                                    class="text-white py-1">
                                                    {{ player.name }}
                                                    <span v-if="player.name === team1Leader" class="text-xs text-green-300">(Leader)</span>
                                                </div>
                                                <div v-if="team1Players.length === 0" class="text-gray-500 py-1">No players yet</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Team 2 -->
                                    <div class="flex flex-col items-center gap-3 p-4 bg-gray-600 rounded border-2 min-w-[200px]" 
                                        :class="{ 'border-red-500': selectedTeam === 2, 'border-gray-500': selectedTeam !== 2 }">
                                        <h4 class="text-white font-medium">Team 2</h4>
                                        
                                        <label class="flex items-center gap-2 text-white cursor-pointer">
                                            <input type="radio" 
                                                name="teamSelection" 
                                                :value="2" 
                                                v-model="selectedTeam"
                                                @change="selectTeam(2)"
                                                :disabled="isTeam2RadioDisabled" />
                                            Join Team 2
                                        </label>
                                        
                                        <div class="text-center">
                                            <div class="text-sm text-gray-300 mb-2">Team Leader:</div>
                                            <div v-if="team2Leader" class="text-green-400 font-medium mb-2">{{ team2Leader }}</div>
                                            <div v-else class="text-yellow-400 text-xs mb-2">No leader selected</div>
                                            
                                            <!-- Become/Unselect Team 2 Leader Button -->
                                            <button v-if="canBecomeTeam2Leader" 
                                                    @click="selectTeamLeader(2)"
                                                    class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                                                Become Team 2 Leader
                                            </button>
                                            
                                            <button v-if="canUnselectTeam2Leader" 
                                                    @click="selectTeamLeader(2)"
                                                    class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                                                Step Down as Leader
                                            </button>
                                        </div>
                                        
                                        <div class="text-center">
                                            <div class="text-sm text-gray-300 mb-1">Players:</div>
                                            <div class="text-xs text-gray-400 max-h-24 overflow-y-auto">
                                                <div v-for="player in team2Players" :key="player.id" 
                                                    :class="{ 'text-green-400 font-semibold': player.name === team2Leader }"
                                                    class="text-white py-1">
                                                    {{ player.name }}
                                                    <span v-if="player.name === team2Leader" class="text-xs text-green-300">(Leader)</span>
                                                </div>
                                                <div v-if="team2Players.length === 0" class="text-gray-500 py-1">No players yet</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Team Assignment Status -->
                                <div class="mt-4 text-center text-sm text-gray-300">
                                    <div v-if="selectedTeam">
                                        You are on Team {{ selectedTeam }}
                                        <span v-if="isTeamLeader" class="text-green-400 font-semibold"> (Leader)</span>
                                    </div>
                                    <div v-else class="text-yellow-400">Please select a team to continue</div>
                                </div>
                            </div>

                            <!-- Lobby Team Leader Selection for 2+ players with AI -->
                            <div v-if="shouldShowLobbyTeamLeader" class="mt-6 p-4 bg-gray-700 rounded-lg">
                                <h3 class="text-white font-semibold text-center mb-4">Lobby Team Leader</h3>
                                
                                <div class="text-center">
                                    <div v-if="lobbyTeamLeader" class="text-green-400 font-medium mb-2">
                                        Lobby Team Leader: {{ lobbyTeamLeader }}
                                    </div>
                                    <div v-else class="text-yellow-400 text-sm mb-3">
                                        Select a lobby team leader to coordinate team answers vs AI
                                    </div>
                                    
                                    <button v-if="!lobbyTeamLeader && !isGameInProgress" 
                                            @click="selectLobbyTeamLeader"
                                            class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                                        Become Lobby Team Leader
                                    </button>
                                </div>
                                
                                <div v-if="lobbyTeamLeader" class="mt-4 text-center">
                                    <div class="text-sm text-gray-300 mb-1">Team Members:</div>
                                    <div class="text-xs text-gray-400">
                                        <div v-for="player in players.filter(p => p.name !== lobbyTeamLeader)" :key="player.id" class="text-white">
                                            {{ player.name }}
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <!-- NON-LEADER VIEW (enhanced for different team modes) -->
                            <div v-else-if="isGameStarted && !gameIsOver" class="basis-full mb-6">
                                <div class="text-center mb-2 text-gray-400 text-sm font-medium">
                                    Question {{ currentQuestionIndex + 1 }} / {{ currentGameQuestions.length }}
                                </div>
                                <div class="text-center mb-4 text-white text-xl font-semibold">
                                    {{ currentGameQuestions[currentQuestionIndex]?.question }}
                                </div>
                                
                                <!-- View-only answer field for non-leaders -->
                                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-4">
                                    <div class="relative w-full sm:w-2/3">
                                        <input 
                                            :value="getWaitingMessage()"
                                            disabled
                                            class="px-4 py-2 rounded w-full bg-gray-600 text-gray-300 cursor-not-allowed"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enhanced Team Mode Indicator -->
                        <div v-if="(isTeamMode || shouldShowLobbyTeamLeader || shouldShowTeamSelection) && playerCount > 1 && showQuestionInput" class="text-center mb-4">
                            <div class="inline-block px-4 py-2 bg-blue-900 text-blue-200 rounded-lg border border-blue-700">
                                <!-- Existing team modes -->
                                <div v-if="joinTeamWithPlayers">
                                    <div v-if="isTeamLeader" class="text-sm font-semibold">
                                        You are the team leader - you submit answers for your team vs AI
                                    </div>
                                    <div v-else class="text-sm font-semibold">
                                        {{ teamLeaderName }} is your team leader vs AI - send suggestions to help
                                    </div>
                                </div>
                                <div v-else-if="joinTeamWithAI">
                                    <div v-if="playerCount === 2">
                                        <div v-if="isTeamLeader" class="text-sm font-semibold">
                                            You + AI are teamed up vs other player
                                        </div>
                                        <div v-else class="text-sm font-semibold">
                                            You are the lobby team leader vs {{ teamLeaderName }} + AI
                                        </div>
                                    </div>
                                    <div v-else>
                                        <div v-if="isTeamLeader" class="text-sm font-semibold">
                                            You + AI are teamed up vs lobby team
                                        </div>
                                        <div v-else-if="isLobbyTeamLeader" class="text-sm font-semibold">
                                            You are the lobby team leader vs {{ teamLeaderName }} + AI
                                        </div>
                                        <div v-else class="text-sm font-semibold">
                                            {{ lobbyTeamLeader }} is your lobby team leader vs {{ teamLeaderName }} + AI
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- New lobby team leader mode (with AI, no team modes) -->
                                <div v-else-if="shouldShowLobbyTeamLeader">
                                    <div v-if="isLobbyTeamLeader" class="text-sm font-semibold">
                                        You are the lobby team leader - coordinate team answers vs AI
                                    </div>
                                    <div v-else-if="lobbyTeamLeader" class="text-sm font-semibold">
                                        {{ lobbyTeamLeader }} is your lobby team leader vs AI - send suggestions to help
                                    </div>
                                </div>
                                
                                <!-- Team selection mode (no AI) -->
                                <div v-else-if="shouldShowTeamSelection">
                                    <div v-if="isTeamLeader" class="text-sm font-semibold">
                                        You are Team {{ selectedTeam }} leader - submit answers for your team
                                    </div>
                                    <div v-else class="text-sm font-semibold">
                                        Send suggestions to your Team {{ selectedTeam }} leader
                                        <span v-if="selectedTeam === 1 && team1Leader">({{ team1Leader }})</span>
                                        <span v-else-if="selectedTeam === 2 && team2Leader">({{ team2Leader }})</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        

                        <div v-if="isWaitingForOthers && !gameIsOver" class="basis-full mb-6 text-center">
                            <div class="p-4 bg-yellow-900 text-yellow-200 rounded border border-yellow-700">
                                <p class="text-lg font-semibold">Waiting for other players...</p>
                                <p class="text-sm mt-2">Please wait while other players complete their actions.</p>
                            </div>
                        </div>

                        <div v-if="playWithAISection" class="flex flex-wrap gap-6 w-full">

                            <div class="flex min-w-[300px] basis-1/4 flex-col gap-4">
                                <div class="bg-gray-800 min-w-[300px] basis-1/4 p-4 rounded border border-gray-700">
                                    <h4 class="text-white font-semibold mb-4">AI Players</h4>
                                    <div class="flex flex-col gap-4 items-center">
                                        <div class="flex flex-row gap-4">
                                            <input type="checkbox" v-model="playWithBespokeAI" @change="updateGameSettings"
                                                :disabled="isGameInProgress || isWaitingForOthers || gameIsOver" />
                                            <label>Play With Learning Model</label>
                                            <input type="checkbox" v-model="playWithAI" @change="updateGameSettings"
                                                :disabled="isGameInProgress || isWaitingForOthers || gameIsOver" />
                                            <label>Play With ChatGPT</label>
                                        </div>
                                        <div v-if="playWithBespokeAI" class="flex flex-row items-center gap-4">
                                            <label for="difficulty">Choose Learning Model:</label>
                                            <select id="difficulty" v-model="selectedDifficulty"
                                                :disabled="isGameInProgress || isWaitingForOthers || gameIsOver"
                                                @change="onDifficultyOrCategoryChange"
                                                class="border rounded px-2 py-1 bg-gray-700 text-white disabled:opacity-50">
                                                <option v-for="difficulty in difficulties" :key="difficulty.id" :value="difficulty.id">
                                                {{ difficulty.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-800 min-w-[300px] max-h-[800px] basis-1/4 p-4 rounded border border-gray-700">

                                    <div class="flex flex-row gap-6">

                                        <div v-if="playWithBespokeAI">
                                            <h4 class="text-white font-semibold mb-2">Bespoke AI Status</h4>

                                            <div v-if="bespokeAILoading" class="text-yellow-400 flex items-center">
                                                <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                        stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                    </path>
                                                </svg>
                                                AI is thinking...
                                            </div>

                                            <div v-if="bespokeAIError" class="text-red-400">
                                                AI Error: {{ aiError }}
                                            </div>

                                            <div v-if="!bespokeAILoading && !bespokeAIError" class="space-y-2">
                                                <div v-for="(question, index) in currentGameQuestions" :key="question.id" class="text-gray-300">
                                                    <span class="font-medium">Q{{ index + 1 }}:</span>
                                                    <span v-if="hasBespokeAIAnswered(index)" class="text-green-400 ml-2">
                                                        {{ bespokeAIAnswers[index]?.answer || 'Answer not available' }}
                                                        <span v-if="bespokeAIAnswers[index]?.score !== undefined && bespokeAIAnswers[index]?.score !== null" 
                                                            class="text-blue-400 ml-1">
                                                            (Score: {{ bespokeAIAnswers[index].score }})
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
                                        </div>

                                        <div v-if="playWithAI">
                                            <h4 class="text-white font-semibold mb-2">ChatGPT Status</h4>

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
                                        </div>
                                    </div>


                                    <!-- <div v-if="playWithAI" class="mt-4 text-xs text-gray-500 bg-gray-900 p-2 rounded">
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
                                    </div> -->
                                </div>
                            </div>

                            <div class="flex-1 min-w-[300px] p-4 bg-gray-800 rounded shadow">
                                <h3 class="font-semibold text-lg mb-4">AI Scores</h3>
                                
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-gray-700">
                                                <th class="p-2 border-b">Model</th>
                                                <th class="p-2 border-b">
                                                    <div class="flex flex-row items-center gap-2">
                                                        <span>Game Session</span>
                                                        <input 
                                                            v-model="aiSearchQuery"
                                                            type="text"
                                                            placeholder="Filter by session..."
                                                            class="px-2 py-1 text-xs bg-gray-600 placeholder-gray-300 text-white rounded border border-gray-500 focus:border-blue-400 focus:outline-none"
                                                            @click.stop
                                                        />
                                                    </div>
                                                </th>
                                                <th class="p-2 border-b">Difficulty</th>
                                                <th class="p-2 border-b">Category</th>
                                                <th class="p-2 border-b cursor-pointer hover:bg-gray-600 transition-colors" 
                                                    @click="sortAITable('score')">
                                                    <div class="flex items-center">
                                                        Score
                                                        <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path v-if="aiSortField === 'score' && aiSortDirection === 'desc'"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                                            <path v-else-if="aiSortField === 'score' && aiSortDirection === 'asc'"
                                                                d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                                            <path v-else
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                                        </svg>
                                                    </div>
                                                </th>
                                                <th class="p-2 border-b cursor-pointer transition-colors" 
                                                    >
                                                    <div class="flex items-center">
                                                        % Score
                                                    </div>
                                                </th>
                                                <th class="p-2 border-b cursor-pointer hover:bg-gray-600 transition-colors" 
                                                    @click="sortAITable('created_at')">
                                                    <div class="flex items-center">
                                                        Date Created
                                                        <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path v-if="aiSortField === 'created_at' && aiSortDirection === 'desc'"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                                            <path v-else-if="aiSortField === 'created_at' && aiSortDirection === 'asc'"
                                                                d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                                            <path v-else
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                                        </svg>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="score in filteredAndSortedAIScores" :key="score.id">
                                                <td class="p-2 border-b text-white">{{ score.model_id || 'Normal' }}</td>
                                                <td class="p-2 border-b text-white">{{ score.session_id }}</td>
                                                <td class="p-2 border-b text-white">
                                                    {{ score.answer_json?.difficulty_name || 'N/A' }}
                                                </td>
                                                <td class="p-2 border-b text-white">
                                                    {{ score.answer_json?.category_name || 'N/A' }}
                                                </td>
                                                <td class="p-2 border-b text-white">{{ score.score }}</td>
                                                <td class="p-2 border-b text-white">{{ calculatePercentage(score) }}%</td>
                                                <td class="p-2 border-b text-white">{{ formatDate(score.created_at) }}</td>
                                            </tr>
                                            <tr v-if="filteredAndSortedAIScores.length === 0">
                                                <td colspan="7" class="p-2 text-center text-gray-400">
                                                    {{ aiSearchQuery.trim() ? 'No matching scores found' : 'No scores available' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <DynamicPagination :currentPage="aiScoresCurrentPage" :totalPages="aiScoresTotalPages"
                                    @change-page="changeAIScoresPage" />
                            </div>
                        </div>


                        
                        <section id="scores">
                        </section>



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
                                <h3 class="font-semibold text-lg mb-4">Player Scores</h3>
                                
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-gray-700">
                                                <th class="p-2 border-b">Player</th>
                                                <th class="p-2 border-b">
                                                    <div class="flex flex-row items-center gap-2">
                                                        <span>Game Session</span>
                                                        <input 
                                                            v-model="playerSearchQuery"
                                                            type="text"
                                                            placeholder="Filter by session..."
                                                            class="px-2 py-1 text-xs bg-gray-600 placeholder-gray-300 text-white rounded border border-gray-500 focus:border-blue-400 focus:outline-none"
                                                            @click.stop
                                                        />
                                                    </div>
                                                </th>
                                                <th class="p-2 border-b">Difficulty</th>
                                                <th class="p-2 border-b">Category</th>
                                                <th class="p-2 border-b cursor-pointer hover:bg-gray-600 transition-colors" 
                                                    @click="sortPlayerTable('score')">
                                                    <div class="flex items-center">
                                                        Score
                                                        <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path v-if="playerSortField === 'score' && playerSortDirection === 'desc'"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                                            <path v-else-if="playerSortField === 'score' && playerSortDirection === 'asc'"
                                                                d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                                            <path v-else
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                                        </svg>
                                                    </div>
                                                </th>
                                                <th class="p-2 border-b cursor-pointer transition-colors" 
                                                    >
                                                    <div class="flex items-center">
                                                        % Score
                                                    </div>
                                                </th>
                                                <th class="p-2 border-b cursor-pointer hover:bg-gray-600 transition-colors" 
                                                    @click="sortPlayerTable('created_at')">
                                                    <div class="flex items-center">
                                                        Date Created
                                                        <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path v-if="playerSortField === 'created_at' && playerSortDirection === 'desc'"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                                            <path v-else-if="playerSortField === 'created_at' && playerSortDirection === 'asc'"
                                                                d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                                            <path v-else
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                                        </svg>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="score in filteredAndSortedGameScores" :key="score.id">
                                                <td class="p-2 border-b text-white">{{ score.user?.name }}</td>
                                                <td class="p-2 border-b text-white">{{ score.session_id }}</td>
                                                <td class="p-2 border-b text-white">
                                                    {{ score.answer_json?.difficulty_name || 'N/A' }}
                                                </td>
                                                <td class="p-2 border-b text-white">
                                                    {{ score.answer_json?.category_name || 'N/A' }}
                                                </td>
                                                <td class="p-2 border-b text-white">{{ score.score }}</td>
                                                <td class="p-2 border-b text-white">{{ calculatePercentage(score) }}%</td>
                                                <td class="p-2 border-b text-white">{{ formatDate(score.created_at) }}</td>
                                            </tr>
                                            <tr v-if="filteredAndSortedGameScores.length === 0">
                                                <td colspan="7" class="p-2 text-center text-gray-400">
                                                    {{ playerSearchQuery.trim() ? 'No matching scores found' : 'No scores available' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <DynamicPagination :currentPage="scoresCurrentPage" :totalPages="scoresTotalPages"
                                    @change-page="changeScoresPage" />
                            </div>
                        </div>

                        <section id="stats">
                        </section>

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