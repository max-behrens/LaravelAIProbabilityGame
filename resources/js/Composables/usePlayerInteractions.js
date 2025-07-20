import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';
import Pusher from 'pusher-js';

export function usePlayerInteractions(gameId, auth) {
  // Reactive state
  const players = ref([]);
  const flashMessages = ref([]);
  const gameState = ref({
    playersReady: new Set(),
    playersAnswered: new Set(),
    playersSubmitted: new Set(),
    currentPlayerCount: 1,
    gameInProgress: false,
    waitingForOthers: false
  });
  const error = ref(null);
  
  // ADD: Store pre-submitted answers for auto-submission
  const preSubmittedAnswers = ref(null);
  
  // Pusher instance
  let pusher = null;
  let gameChannel = null;
  let pusherConnected = false;
  
  // Add callback refs for external updates
  const callbacks = ref({
    onScoresUpdate: null,
    onGameUpdate: null,
    onChartsUpdate: null
  });
  
  const currentUserId = computed(() => auth?.user?.id ?? null);
  const currentUserName = computed(() => auth?.user?.name ?? 'Unknown');
  const isInGame = computed(() => 
    players.value.some(player => player.id === currentUserId.value)
  );

  // Enhanced debug logging
  console.log('=== Player Interactions Setup ===');
  console.log('Game ID:', gameId);
  console.log('Current User ID:', currentUserId.value);
  console.log('Current User Name:', currentUserName.value);

  // Flash message management with duplicate prevention
  const addFlashMessage = (message, type = 'info') => {
    // Check for duplicate messages (same message and type within last 2 seconds)
    const now = Date.now();
    const isDuplicate = flashMessages.value.some(msg => 
      msg.message === message && 
      msg.type === type && 
      (now - msg.timestamp.getTime()) < 2000
    );
    
    if (isDuplicate) {
      console.log('Duplicate flash message prevented:', message);
      return;
    }

    const flashMessage = {
      id: Date.now() + Math.random(),
      message,
      type,
      timestamp: new Date()
    };
    
    flashMessages.value.push(flashMessage);
    console.log('Flash message added:', flashMessage);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
      removeFlashMessage(flashMessage.id);
    }, 5000);
  };

  const removeFlashMessage = (id) => {
    const index = flashMessages.value.findIndex(msg => msg.id === id);
    if (index > -1) {
      flashMessages.value.splice(index, 1);
      console.log('Flash message removed:', id);
    }
  };

  const clearFlashMessages = () => {
    flashMessages.value = [];
    console.log('All flash messages cleared');
  };

  // Register callbacks for external components
  const registerCallbacks = (newCallbacks) => {
    callbacks.value = { ...callbacks.value, ...newCallbacks };
    console.log('Callbacks registered:', Object.keys(newCallbacks));
  };

  // Trigger external updates
  const triggerScoresUpdate = async () => {
    if (callbacks.value.onScoresUpdate) {
      console.log('🔄 Triggering scores update...');
      await callbacks.value.onScoresUpdate();
    }
  };

  const triggerGameUpdate = async () => {
    if (callbacks.value.onGameUpdate) {
      console.log('🔄 Triggering game update...');
      await callbacks.value.onGameUpdate();
    }
  };

  const triggerChartsUpdate = async () => {
    if (callbacks.value.onChartsUpdate) {
      console.log('🔄 Triggering charts update...');
      await callbacks.value.onChartsUpdate();
    }
  };

  // Fetch current players - Fixed API route
  const fetchPlayers = async () => {
    try {
      console.log('Fetching players for game:', gameId);
      const response = await axios.get(`/api/games/${gameId}/players`);
      players.value = response.data;
      console.log('Players fetched:', response.data);
      return response.data;
    } catch (err) {
      error.value = err;
      addFlashMessage('Failed to load players.', 'error');
      console.error('Failed to fetch players:', err);
    }
  };

  // Change player count
  const changePlayerCount = async (count) => {
    if (!isInGame.value) {
      console.log('User not in game, cannot change player count');
      return;
    }
    
    console.log('Changing player count to:', count);
    gameState.value.currentPlayerCount = count;

    // Broadcast player count change
    try {
      const eventData = {
        userId: currentUserId.value,
        userName: currentUserName.value,
        playerCount: count,
        timestamp: new Date().toISOString()
      };

      // Trigger Pusher event directly instead of going through backend
      if (gameChannel) {
        // Note: This won't work with Pusher's client events unless you enable them
        // Better to call a backend endpoint that triggers the event
        await axios.post(`/api/games/${gameId}/broadcast`, {
          event: 'player.count.changed',
          data: eventData
        });
      }
    } catch (err) {
      console.error('Failed to broadcast player count change:', err);
    }
  };

  // Answer question
  const answerQuestion = async (questionIndex, answer) => {
    if (!isInGame.value) return;
    
    gameState.value.playersAnswered.add(`${currentUserId.value}-${questionIndex}`);
    
    // Broadcast answer
    try {
      await axios.post(`/api/games/${gameId}/broadcast`, {
        event: 'player.answered',
        data: {
          userId: currentUserId.value,
          userName: currentUserName.value,
          questionIndex: questionIndex + 1,
          timestamp: new Date().toISOString()
        }
      });
    } catch (err) {
      console.error('Failed to broadcast answer:', err);
    }
  };

  // FIXED: Submit answers with proper auto-submission logic
  const submitAnswers = async (answers, playerCount) => {
    if (!isInGame.value) return { submitted: false, waitingForOthers: false };
    
    try {
      if (playerCount === 1) {
        // Single player - submit immediately
        await axios.post(`/games/${gameId}/submit-answer`, { answers });

        // Broadcast completion
        await axios.post(`/api/games/${gameId}/broadcast`, {
          event: 'game.completed.single',
          data: {
            userId: currentUserId.value,
            userName: currentUserName.value,
            timestamp: new Date().toISOString()
          }
        });
        
        addFlashMessage('Answers submitted successfully!', 'success');
        
        // Trigger updates immediately for single player
        await Promise.all([
          triggerScoresUpdate(),
          triggerGameUpdate(),
          triggerChartsUpdate()
        ]);
        
        resetGameState();
        return { submitted: true, waitingForOthers: false };
        
      } else {
        // MULTIPLAYER: Store answers for potential auto-submission
        console.log('💾 Storing answers for potential auto-submission:', answers);
        preSubmittedAnswers.value = [...answers]; // Create a copy
        gameState.value.playersSubmitted.add(currentUserId.value);

        // Broadcast submission
        await axios.post(`/api/games/${gameId}/broadcast`, {
          event: 'player.submitted',
          data: {
            userId: currentUserId.value,
            userName: currentUserName.value,
            submittedCount: gameState.value.playersSubmitted.size,
            requiredCount: playerCount,
            timestamp: new Date().toISOString()
          }
        });
      
        // Check if all players have submitted
        if (gameState.value.playersSubmitted.size >= playerCount) {
          // All players submitted - save scores immediately
          console.log('✅ All players submitted - saving scores immediately');
          await axios.post(`/games/${gameId}/submit-answer`, { answers });
      
          // Broadcast game completion
          await axios.post(`/api/games/${gameId}/broadcast`, {
            event: 'game.completed.multiplayer',
            data: {
              playerCount: playerCount,
              timestamp: new Date().toISOString()
            }
          });
      
          addFlashMessage('All players submitted! Game completed!', 'success');
      
          // Trigger updates
          await Promise.all([
            triggerScoresUpdate(),
            triggerGameUpdate(),
            triggerChartsUpdate()
          ]);
      
          // Clear pre-submitted answers and reset
          preSubmittedAnswers.value = null;
          resetGameState();
          return { submitted: true, waitingForOthers: false };
      
        } else {
          // Waiting for others - answers are stored for auto-submission
          addFlashMessage(`Waiting for other players to submit... (${gameState.value.playersSubmitted.size}/${playerCount})`, 'info');
          return { submitted: false, waitingForOthers: true, preSubmitted: true };
        }
      }
      
    } catch (err) {
      error.value = err;
      addFlashMessage('Failed to submit answers: ' + (err.response?.data?.message || err.message), 'error');
      console.error('Failed to submit answers:', err);
      return { submitted: false, waitingForOthers: false };
    }
  };

  // Reset game state
  const resetGameState = () => {
    console.log('Resetting game state');
    gameState.value.playersReady.clear();
    gameState.value.playersAnswered.clear();
    gameState.value.playersSubmitted.clear();
    gameState.value.gameInProgress = false;
    gameState.value.waitingForOthers = false;
    // Clear pre-submitted answers
    preSubmittedAnswers.value = null;
  };

  // Enhanced Pusher setup with better connection handling
  const setupPusher = () => {
    console.log('=== Setting up Pusher ===');
    
    // Enable Pusher logging for debugging
    Pusher.logToConsole = true;
    
    pusher = new Pusher('c493e35de663a696d88e', {
      cluster: 'eu',
      encrypted: true,
      authEndpoint: '/broadcasting/auth',
      enabledTransports: ['ws', 'wss']
    });

    const channelName = `game.${gameId}`;
    console.log('Subscribing to channel:', channelName);
    
    // Add connection event handlers first
    pusher.connection.bind('connected', () => {
      console.log('✅ Pusher connected successfully');
      pusherConnected = true;
    });
    
    pusher.connection.bind('connecting', () => {
      console.log('🔄 Pusher connecting...');
    });
    
    pusher.connection.bind('disconnected', () => {
      console.log('❌ Pusher disconnected');
      pusherConnected = false;
    });
    
    pusher.connection.bind('error', (err) => {
      console.error('❌ Pusher connection error:', err);
      pusherConnected = false;
    });

    pusher.connection.bind('state_change', (states) => {
      console.log('Pusher state changed from', states.previous, 'to', states.current);
    });

    // Subscribe to the channel
    gameChannel = pusher.subscribe(channelName);
    
    // Add channel-specific event handlers
    gameChannel.bind('pusher:subscription_succeeded', () => {
      console.log('✅ Successfully subscribed to channel:', channelName);
    });

    gameChannel.bind('pusher:subscription_error', (error) => {
      console.error('❌ Failed to subscribe to channel:', channelName, error);
    });

    // Player joined - Fixed to show correct messages
    gameChannel.bind('player.joined', (data) => {
      console.log('🔔 Received player.joined event:', data);
      console.log('Current user ID:', currentUserId.value, 'Event user ID:', data.userId);
      
      if (data.userId !== currentUserId.value) {
        addFlashMessage(`${data.userName} joined the game!`, 'info');
        // Refresh player list after a small delay
        setTimeout(() => {
          fetchPlayers();
        }, 500);
      }
      // Don't show any message for own actions
    });

    // Player left - Fixed to show correct messages
    gameChannel.bind('player.left', (data) => {
      console.log('🔔 Received player.left event:', data);
      console.log('Current user ID:', currentUserId.value, 'Event user ID:', data.userId);
      
      if (data.userId !== currentUserId.value) {
        addFlashMessage(`${data.userName} left the game.`, 'info');
        // Refresh player list after a small delay
        setTimeout(() => {
          fetchPlayers();
        }, 500);
      }
      // Don't show any message for own actions
    });

    // Player count changed
    gameChannel.bind('player.count.changed', (data) => {
      console.log('🔔 Received player.count.changed event:', data);
      if (data.userId !== currentUserId.value) {
        addFlashMessage(`${data.userName} changed player count to ${data.playerCount}`, 'info');
      }
    });

    // Player ready (multiplayer start)
    gameChannel.bind('player.ready', (data) => {
      console.log('🔔 Received player.ready event:', data);
      if (data.userId !== currentUserId.value) {
        gameState.value.playersReady.add(data.userId);
        addFlashMessage(`${data.userName} clicked Start Game! (${data.readyCount}/${data.requiredCount} ready)`, 'info');
      }
    });

    // Single player game started
    gameChannel.bind('game.started.single', (data) => {
      console.log('🔔 Received game.started.single event:', data);
      if (data.userId !== currentUserId.value) {
        addFlashMessage(`${data.userName} started a single-player game. Please wait for them to finish.`, 'warning');
        gameState.value.gameInProgress = true;
      }
    });

    // Multiplayer game started
    gameChannel.bind('game.started.multiplayer', (data) => {
      console.log('🔔 Received game.started.multiplayer event:', data);
      if (!gameState.value.playersReady.has(currentUserId.value)) {
        addFlashMessage(`Game started with ${data.playerCount} players!`, 'success');
        gameState.value.gameInProgress = true;
        gameState.value.waitingForOthers = false;
      }
    });

    // Player answered question
    gameChannel.bind('player.answered', (data) => {
      console.log('🔔 Received player.answered event:', data);
      if (data.userId !== currentUserId.value) {
        addFlashMessage(`${data.userName} answered question ${data.questionIndex}`, 'info');
      }
    });

    // FIXED: Player submitted (multiplayer) - Auto-submission logic
    gameChannel.bind('player.submitted', async (data) => {
      console.log('🔔 Received player.submitted event:', data);
      
      if (data.userId !== currentUserId.value) {
        // Another player submitted
        gameState.value.playersSubmitted.add(data.userId);
        addFlashMessage(`${data.userName} submitted their answers! (${data.submittedCount}/${data.requiredCount} submitted)`, 'info');
        
        // Check if all required players have now submitted
        const allPlayersSubmitted = data.submittedCount >= data.requiredCount;
        const iHavePreSubmitted = preSubmittedAnswers.value !== null;
        const iAlreadyReallySubmitted = gameState.value.playersSubmitted.has(currentUserId.value);
        
        console.log('Auto-submission check:', {
          allPlayersSubmitted,
          iHavePreSubmitted,
          iAlreadyReallySubmitted,
          submittedCount: data.submittedCount,
          requiredCount: data.requiredCount
        });
        
        if (allPlayersSubmitted && iHavePreSubmitted && iAlreadyReallySubmitted) {
          console.log('🚀 Auto-submitting pre-saved answers now that all players are done...');
          try {
            // Submit the stored answers
            await axios.post(`/games/${gameId}/submit-answer`, {
              answers: preSubmittedAnswers.value
            });
    
            // Broadcast game completion
            await axios.post(`/api/games/${gameId}/broadcast`, {
              event: 'game.completed.multiplayer',
              data: {
                playerCount: data.requiredCount,
                timestamp: new Date().toISOString()
              }
            });
    
            addFlashMessage('Your answers have been auto-submitted! Game completed!', 'success');
            
            // Trigger updates
            await Promise.all([
              triggerScoresUpdate(),
              triggerGameUpdate(),
              triggerChartsUpdate()
            ]);
    
            // 🆕 TRIGGER UI RESET FOR AUTO-SUBMITTED USER
            // Call the external callback to reset the Vue component state
            if (callbacks.value.onGameComplete) {
              console.log('🔄 Triggering UI reset for auto-submitted user...');
              await callbacks.value.onGameComplete();
            }
    
            // Clean up
            preSubmittedAnswers.value = null;
            resetGameState();
    
          } catch (err) {
            console.error('Auto-submission failed:', err);
            addFlashMessage('Failed to auto-submit your answers. Please try manually.', 'error');
          }
        }
      }
    });

    // FIXED: Single player game completed - Trigger score updates for ALL players
    gameChannel.bind('game.completed.single', async (data) => {
      console.log('🔔 Received game.completed.single event:', data);
      // Update scores for EVERYONE, not just other players
      addFlashMessage(`${data.userName} completed their game!`, 'success');
      gameState.value.gameInProgress = false;
      
      // TRIGGER LIVE UPDATES FOR ALL PLAYERS (including the one who submitted)
      console.log('🔄 Triggering live updates for single player completion...');
      await Promise.all([
        triggerScoresUpdate(),
        triggerGameUpdate(),
        triggerChartsUpdate()
      ]);
    });

    // FIXED: Multiplayer game completed - Trigger score updates for ALL players
    gameChannel.bind('game.completed.multiplayer', async (data) => {
      console.log('🔔 Received game.completed.multiplayer event:', data);
      
      // Update for all players who haven't been through the auto-submission process
      if (preSubmittedAnswers.value === null && !gameState.value.playersSubmitted.has(currentUserId.value)) {
        addFlashMessage(`Game completed with all ${data.playerCount} players!`, 'success');
      }
      
      // TRIGGER LIVE UPDATES FOR ALL PLAYERS
      console.log('🔄 Triggering live updates for multiplayer completion...');
      await Promise.all([
        triggerScoresUpdate(),
        triggerGameUpdate(),
        triggerChartsUpdate()
      ]);
      
      resetGameState();
    });

    // Bind to all events for debugging
    gameChannel.bind_global((eventName, data) => {
      console.log('🌐 Global event received:', eventName, data);
    });

    console.log('✅ Pusher event bindings completed');
  };

  // Cleanup
  const cleanup = () => {
    console.log('🧹 Cleaning up Pusher connections');
    if (gameChannel) {
      gameChannel.unbind_all();
      pusher.unsubscribe(`game.${gameId}`);
    }
    if (pusher) {
      pusher.disconnect();
    }
    pusherConnected = false;
  };

  // Lifecycle hooks
  onMounted(() => {
    console.log('🚀 usePlayerInteractions mounted');
    fetchPlayers();
    setupPusher();
  });

  onUnmounted(() => {
    console.log('🛑 usePlayerInteractions unmounted');
    cleanup();
  });

  return {
    // State
    players,
    flashMessages,
    gameState,
    error,
    isInGame,
    preSubmittedAnswers, // Expose for debugging if needed
    
    // Methods
    fetchPlayers,
    changePlayerCount,
    answerQuestion,
    submitAnswers,
    resetGameState,
    addFlashMessage,
    removeFlashMessage,
    clearFlashMessages,
    registerCallbacks,
    
    // Cleanup
    cleanup
  };
}