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
    //  Track players who have pre-answered each question
    playersPreAnswered: new Map(), // questionIndex -> Set of userIds
    currentPlayerCount: 1,
    gameInProgress: false,
    waitingForOthers: false,
    gameSettings: null,
    starterName: null
  });
  const error = ref(null);
  
  const preReadyPlayers = ref(new Set());
  const isWaitingToStart = ref(false);

  // Store pre-submitted answers for auto-submission
  const preSubmittedAnswers = ref(null);
  const preSubmittedAIAnswers = ref(null);
  
  //  Store pre-answered questions for auto-progression
  const preAnsweredQuestions = ref(new Map()); // questionIndex -> answer
  
  // AI Module reference - will be set by main component
  let aiModule = null;
  
  // Pusher instance
  let pusher = null;
  let gameChannel = null;
  let pusherConnected = false;
  
  // Add callback refs for external updates
  const callbacks = ref({
    onScoresUpdate: null,
    onGameUpdate: null,
    onChartsUpdate: null,
    onGameComplete: null,
    onQuestionProgress: null // Enhanced to handle auto-progression
  });
  
  const currentUserId = computed(() => auth?.user?.id ?? null);
  const currentUserName = computed(() => auth?.user?.name ?? 'Unknown');
  const isInGame = computed(() => 
    players.value.some(player => player.id === currentUserId.value)
  );

  // Function to set AI module reference
  const setAIModule = (aiModuleRef) => {
    aiModule = aiModuleRef;
    console.log('AI Module set in usePlayerInteractions:', !!aiModule);
  };

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

  //  Trigger question progression
  const triggerQuestionProgress = async (questionIndex, allAnswers = null) => {
    if (callbacks.value.onQuestionProgress) {
      console.log('🔄 Triggering question progress for question:', questionIndex);
      await callbacks.value.onQuestionProgress(questionIndex, allAnswers);
    }
  };

  // Helper function to prepare AI answers for submission
  const prepareAIAnswersForSubmission = () => {
    if (!aiModule || !aiModule.playWithAI.value) {
      console.log('No AI module or AI not enabled');
      return [];
    }
  
    const aiAnswersObj = aiModule.aiAnswers.value;
    const aiAnswersArray = [];
  
    // Convert object to array, ensuring proper indexing
    for (let i = 0; i < Object.keys(aiAnswersObj).length; i++) {
      aiAnswersArray[i] = aiAnswersObj[i]?.answer ?? null;
    }
  
    console.log('Prepared AI answers for submission:', aiAnswersArray);
    return aiAnswersArray;
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

      // NEW: Store player count preference in backend cache
      try {
        console.log('Storing player count preference in cache:', count);
        await axios.post(`/api/games/${gameId}/store-player-count`, {
          playerCount: count,
          userId: currentUserId.value
        });
        
        console.log('Player count preference stored in cache');
      } catch (err) {
        console.error('Failed to store player count preference:', err);
      }

      // Broadcast player count change
      try {
        const eventData = {
          userId: currentUserId.value,
          userName: currentUserName.value,
          playerCount: count,
          timestamp: new Date().toISOString()
        };

        await axios.post(`/api/games/${gameId}/broadcast`, {
          event: 'player.count.changed',
          data: eventData
        });
      } catch (err) {
        console.error('Failed to broadcast player count change:', err);
      }
  };

  // Updated answerQuestion function with difficulty and category support
  const answerQuestion = async (questionIndex, answer, playerCount = 1) => {
    if (!isInGame.value) return { submitted: false, waitingForOthers: false };
    
    try {
      if (playerCount === 1) {
        // Single player - answer immediately
        gameState.value.playersAnswered.add(`${currentUserId.value}-${questionIndex}`);
        
        // Broadcast answer info
        await axios.post(`/api/games/${gameId}/broadcast`, {
          event: 'player.answered',
          data: {
            userId: currentUserId.value,
            userName: currentUserName.value,
            questionIndex: questionIndex + 1,
            timestamp: new Date().toISOString()
          }
        });
        
        return { submitted: true, waitingForOthers: false };
        
      } else {
        // MULTIPLAYER: Store answer for potential auto-submission

        console.log('💾 Storing answer for potential auto-progression:', { 
          questionIndex, 
          answer,

        });
        
        preAnsweredQuestions.value.set(questionIndex, {
          answer: answer,
        });
        
        // Initialize the set for this question if it doesn't exist
        if (!gameState.value.playersPreAnswered.has(questionIndex)) {
          gameState.value.playersPreAnswered.set(questionIndex, new Set());
        }
        gameState.value.playersPreAnswered.get(questionIndex).add(currentUserId.value);

        // Broadcast pre-answer info
        await axios.post(`/api/games/${gameId}/broadcast`, {
          event: 'player.pre.answered',
          data: {
            userId: currentUserId.value,
            userName: currentUserName.value,
            questionIndex: questionIndex,
            answeredCount: gameState.value.playersPreAnswered.get(questionIndex).size,
            requiredCount: playerCount,
            timestamp: new Date().toISOString()
          }
        });

        // Check if all players have pre-answered this question
        const preAnsweredCount = gameState.value.playersPreAnswered.get(questionIndex).size;
        if (preAnsweredCount >= playerCount) {
          console.log('✅ All players pre-answered question', questionIndex, '- triggering progression');
          
          // All players pre-answered - mark as officially answered for everyone
          gameState.value.playersAnswered.add(`${currentUserId.value}-${questionIndex}`);
          
          // Broadcast that all players can progress
          await axios.post(`/api/games/${gameId}/broadcast`, {
            event: 'question.all.answered',
            data: {
              questionIndex: questionIndex,
              playerCount: playerCount,
              timestamp: new Date().toISOString()
            }
          });
          
          addFlashMessage('All players answered! Moving forward...', 'success');
          
          // Clear pre-answered data for this question
          preAnsweredQuestions.value.delete(questionIndex);
          gameState.value.playersPreAnswered.delete(questionIndex);
          
          return { submitted: true, waitingForOthers: false };
          
        } else {
          // Waiting for others - answer is stored for auto-progression
          addFlashMessage(`Waiting for other players to answer... (${preAnsweredCount}/${playerCount})`, 'info');
          return { submitted: false, waitingForOthers: true, preAnswered: true };
        }
      }
      
    } catch (err) {
      error.value = err;
      addFlashMessage('Failed to answer question: ' + (err.response?.data?.message || err.message), 'error');
      console.error('Failed to answer question:', err);
      return { submitted: false, waitingForOthers: false };
    }
  };

  // Updated submitAnswers function with difficulty and category support
const submitAnswers = async (answers, playerCount, difficultyId = null, categoryId = null) => {
    if (!isInGame.value) return { submitted: false, waitingForOthers: false };

    try {
        const aiAnswers = prepareAIAnswersForSubmission();

        if (playerCount === 1) {
            // SINGLE PLAYER LOGIC - FIXED TO ENSURE SUBMISSION
            console.log('🚀 Single player submitting answers...');
            
            const requestData = {
                answers: answers,
                difficulty_id: difficultyId,
                category_id: categoryId
            };

            // Add AI answers if playing with AI
            if (aiModule?.playWithAI.value) {
                requestData.aiAnswers = aiAnswers;
                requestData.playWithAI = true;
                console.log('Including AI answers in single-player submission:', aiAnswers);
            }

            console.log('🚀 Single player submission data:', requestData);
            
            // CRITICAL: Actually submit to the server
            const response = await axios.post(`/games/${gameId}/submit-answer`, requestData);
            
            console.log('✅ Single player submission response:', response.data);

            // Broadcast game completion to other players
            try {
                await axios.post(`/api/games/${gameId}/broadcast`, {
                    event: 'game.completed.single',
                    data: {
                        userId: currentUserId.value,
                        userName: currentUserName.value,
                        timestamp: new Date().toISOString(),
                        difficultyId: difficultyId,
                        categoryId: categoryId,
                        playedWithAI: aiModule?.playWithAI.value || false
                    }
                });
                console.log('✅ Single-player completion broadcasted to other players');
            } catch (broadcastError) {
                console.error('Failed to broadcast single-player completion:', broadcastError);
                // Don't fail the submission if broadcast fails
            }

            addFlashMessage('Game completed successfully!', 'success');
            
            // Reset game state after successful submission
            setTimeout(() => {
                resetGameState();
                console.log('Single player game state reset after completion');
            }, 1000);
            
            return { submitted: true, waitingForOthers: false };

        } else {
            // MULTIPLAYER LOGIC - Keep existing logic
            console.log('💾 Multiplayer: Submitting answers immediately');

            const cleanAnswers = answers.map(answer => (answer === undefined ? '' : answer));
            
            const requestData = {
                answers: cleanAnswers,
                difficulty_id: difficultyId,
                category_id: categoryId
            };

            if (aiModule?.playWithAI.value) {
                requestData.aiAnswers = aiAnswers;
                requestData.playWithAI = true;
            }

            // SUBMIT TO SERVER IMMEDIATELY FOR ALL PLAYERS
            console.log('🚀 Multiplayer player submitting to server:', requestData);
            await axios.post(`/games/${gameId}/submit-answer`, requestData);

            // Check if this is the last player to submit
            const currentSubmissionCount = gameState.value.playersSubmitted.size;
            const isLastPlayer = (currentSubmissionCount + 1) >= playerCount;

            // Add this player to the submitted set
            gameState.value.playersSubmitted.add(currentUserId.value);
            
            // Broadcast submission status
            await axios.post(`/api/games/${gameId}/broadcast`, {
                event: 'player.submitted',
                data: {
                    userId: currentUserId.value,
                    userName: currentUserName.value,
                    submittedCount: gameState.value.playersSubmitted.size,
                    requiredCount: playerCount,
                    difficultyId: difficultyId,
                    categoryId: categoryId,
                    timestamp: new Date().toISOString()
                }
            });

            if (isLastPlayer) {
                console.log('✅ Last player - broadcasting game completion');
                
                // Broadcast final completion
                await axios.post(`/api/games/${gameId}/broadcast`, {
                    event: 'game.completed.multiplayer',
                    data: {
                        playerCount: playerCount,
                        timestamp: new Date().toISOString()
                    }
                });

                addFlashMessage('All players submitted! Game completed!', 'success');
                resetGameState();
                return { submitted: true, waitingForOthers: false };
            } else {
                // Not the last player - wait for completion event
                addFlashMessage(`Your answers submitted! Waiting for other players... (${gameState.value.playersSubmitted.size}/${playerCount})`, 'info');
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

  // Update the resetGameState function in usePlayerInteractions.js
  const resetGameState = () => {
      console.log('Resetting game state');
      gameState.value.playersReady.clear();
      gameState.value.playersAnswered.clear();
      gameState.value.playersSubmitted.clear();
      gameState.value.playersPreAnswered.clear();
      gameState.value.gameInProgress = false;
      gameState.value.waitingForOthers = false;
      
      // Clear pre-submitted answers and pre-answered questions
      preSubmittedAnswers.value = null;
      preSubmittedAIAnswers.value = null;
      preAnsweredQuestions.value.clear();
      
      // Clear ready state
      preReadyPlayers.value.clear();
      isWaitingToStart.value = false;
  };

  // Enhanced Pusher setup with better connection handling
  const setupPusher = () => {
    console.log('=== Setting up Pusher ===');
    
    
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
            // Another player clicked start
            gameState.value.playersReady.add(data.userId);
            
          // NEW: Store game settings and starter info
            if (data.gameSettings) {
                gameState.value.gameSettings = data.gameSettings;
                gameState.value.starterName = data.gameSettings.starter_name;
                console.log('Game settings received from ready player:', data.gameSettings);
            }
            
            addFlashMessage(`${data.userName} is ready to start! (${data.readyCount}/${data.requiredCount} ready)`, 'info');
            
          // Check if all required players are now ready for auto-start
                const allPlayersReady = data.readyCount >= data.requiredCount;
                const iAmReady = preReadyPlayers.value.has(currentUserId.value);
                const gameNotStartedYet = !gameState.value.gameInProgress;
                
                console.log('Auto-start check:', {
                    allPlayersReady,
                    iAmReady,
                    gameNotStartedYet,
                    readyCount: data.readyCount,
                    requiredCount: data.requiredCount,
                    isWaitingToStart: isWaitingToStart.value
                });
                
                if (allPlayersReady && iAmReady && gameNotStartedYet && isWaitingToStart.value) {
                    console.log('Auto-starting game for ready player via callback...');
                    
                    // Use callback to notify main component to start the game
                    if (callbacks.value.onAutoStart) {
                        callbacks.value.onAutoStart();
                }
            }
        }
    });

  // Also update the game.started.all.ready handler
  gameChannel.bind('game.started.all.ready', async (data) => {
      console.log('🔔 Received game.started.all.ready event:', data);
      
      // Apply game settings for all players
      if (data.gameSettings && callbacks.value.onApplyGameSettings) {
          await callbacks.value.onApplyGameSettings(data.gameSettings);
      }
      
      // If I'm waiting and this event is received, trigger auto-start
      if (isWaitingToStart.value) {
          console.log('🔄 Auto-starting game via all.ready callback...');
          
          // Use callback to notify main component to start the game
          if (callbacks.value.onAutoStart) {
              callbacks.value.onAutoStart();
          }
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

    // Player answered question (existing logic)
    gameChannel.bind('player.answered', (data) => {
      console.log('🔔 Received player.answered event:', data);
      if (data.userId !== currentUserId.value) {
        addFlashMessage(`${data.userName} answered question ${data.questionIndex}`, 'info');
      }
    });

    //  Player pre-answered question (multiplayer auto-progression)
    gameChannel.bind('player.pre.answered', async (data) => {
      console.log('🔔 Received player.pre.answered event:', data);
      
      if (data.userId !== currentUserId.value) {
        // Another player pre-answered
        if (!gameState.value.playersPreAnswered.has(data.questionIndex)) {
          gameState.value.playersPreAnswered.set(data.questionIndex, new Set());
        }
        gameState.value.playersPreAnswered.get(data.questionIndex).add(data.userId);
        
        addFlashMessage(`${data.userName} answered question ${data.questionIndex + 1}! (${data.answeredCount}/${data.requiredCount} answered)`, 'info');
        
        // Check if all required players have now answered
        const allPlayersAnswered = data.answeredCount >= data.requiredCount;
        const iHavePreAnswered = preAnsweredQuestions.value.has(data.questionIndex);
        const iAlreadyReallyAnswered = gameState.value.playersAnswered.has(`${currentUserId.value}-${data.questionIndex}`);
        
        console.log('Auto-progression check:', {
          allPlayersAnswered,
          iHavePreAnswered,
          iAlreadyReallyAnswered,
          answeredCount: data.answeredCount,
          requiredCount: data.requiredCount,
          questionIndex: data.questionIndex
        });
        
        if (allPlayersAnswered && iHavePreAnswered && !iAlreadyReallyAnswered) {
          console.log('🚀 Auto-progressing pre-saved answer for question', data.questionIndex);
          try {
            // Mark as officially answered
            gameState.value.playersAnswered.add(`${currentUserId.value}-${data.questionIndex}`);
            
            // Broadcast that all players can progress
            await axios.post(`/api/games/${gameId}/broadcast`, {
              event: 'question.all.answered',
              data: {
                questionIndex: data.questionIndex,
                playerCount: data.requiredCount,
                timestamp: new Date().toISOString()
              }
            });
    
            addFlashMessage('Your answer has been auto-submitted! Moving forward...', 'success');
            
            // Clean up pre-answered data
            preAnsweredQuestions.value.delete(data.questionIndex);
            gameState.value.playersPreAnswered.delete(data.questionIndex);
    
          } catch (err) {
            console.error('Auto-progression failed:', err);
            addFlashMessage('Failed to auto-progress your answer. Please try manually.', 'error');
          }
        }
      }
    });

    //  All players answered a question
    gameChannel.bind('question.all.answered', async (data) => {
      console.log('🔔 Received question.all.answered event:', data);
      
      // Trigger question progression for all players who haven't progressed yet
      if (preAnsweredQuestions.value.has(data.questionIndex)) {
        console.log('🔄 Triggering auto-progression for question:', data.questionIndex);
        
        // Get all current answers to pass to the callback
        const allAnswers = Array.from(preAnsweredQuestions.value.entries());
        
        // Clean up this question's pre-answered data
        preAnsweredQuestions.value.delete(data.questionIndex);
        gameState.value.playersPreAnswered.delete(data.questionIndex);
        
        // Trigger external callback to progress to next question
        await triggerQuestionProgress(data.questionIndex, allAnswers);
      }
    });

    // AI answered event
    gameChannel.bind('ai.answered', (data) => {
        console.log('🔔 Received ai.answered event:', data);
        
        // CRITICAL FIX: Store the AI answer with ALL properties
        if (aiModule && data.aiAnswer) {
            // Ensure the aiAnswers object exists for this question index
            if (!aiModule.aiAnswers.value[data.questionIndex]) {
                aiModule.aiAnswers.value[data.questionIndex] = {};
            }
            
            // Store ALL the AI answer data
            aiModule.aiAnswers.value[data.questionIndex] = {
                answer: data.aiAnswer,
                score: data.aiScore, 
                isCorrect: data.isCorrect,
                cached: false, // Mark as received via broadcast
                questionIndex: data.questionIndex, // Add question index for reference
                timestamp: data.timestamp || new Date().toISOString()
            };
            
            console.log('AI answer stored with complete data:', {
                questionIndex: data.questionIndex,
                answer: data.aiAnswer,
                score: data.aiScore,
                isCorrect: data.isCorrect,
                storedData: aiModule.aiAnswers.value[data.questionIndex]
            });
            
            // Force reactivity update
            aiModule.aiAnswers.value = { ...aiModule.aiAnswers.value };
        } else {
            console.warn('AI module not available or AI answer data incomplete:', {
                hasAiModule: !!aiModule,
                hasAiAnswer: !!data.aiAnswer,
                data
            });
        }
        
        // Trigger external callback to progress to next question
        if (callbacks.value.onQuestionProgress) {
            callbacks.value.onQuestionProgress(data.questionIndex);
        }
        
        addFlashMessage('AI answered! Moving to next question...', 'success');
    });
      
  // Updated Pusher event handler for player.submitted with difficulty/category support
  gameChannel.bind('player.submitted', async (data) => {
    console.log('🔔 Received player.submitted event:', data);
    
    if (data.userId !== currentUserId.value) {
      gameState.value.playersSubmitted.add(data.userId);

      // Provide UI feedback to the current user
      addFlashMessage(`${data.userName} submitted their answers! (${data.submittedCount}/${data.requiredCount} submitted)`, 'info');
     
    }
  });

    // FIXED: Single player game completed - Trigger score updates for ALL players
    gameChannel.bind('game.completed.single', async (data) => {
      console.log('🔔 Received game.completed.single event:', data);
      
      // CRITICAL: Reset gameInProgress state so the indicator disappears
      gameState.value.gameInProgress = false;
      
      // Update scores for EVERYONE, not just other players
      addFlashMessage(`${data.userName} completed their game!`, 'success');
      
      // TRIGGER LIVE UPDATES FOR ALL PLAYERS (including the one who submitted)
      console.log('🔄 Triggering live updates for single player completion...');
      await Promise.all([
        triggerScoresUpdate(),
        triggerGameUpdate(),
        triggerChartsUpdate()
      ]);
      
      // Clear any ready state that might be lingering
      preReadyPlayers.value.clear();
      isWaitingToStart.value = false;
      
      console.log('✅ Single player game completion processed - room is now available');
    });

    // SIMPLIFIED: Multiplayer game completed - No more auto-submission needed
    gameChannel.bind('game.completed.multiplayer', async (data) => {
        console.log('🔔 Received game.completed.multiplayer event:', data);
        
        // All players have already submitted their answers individually
        // This event just triggers UI updates and cleanup
        
        addFlashMessage('Game completed! All players have submitted their answers.', 'success');
        
        // Trigger live updates for all players
        console.log('🔄 Triggering live updates for multiplayer completion...');
        await Promise.all([
            triggerScoresUpdate(),
            triggerGameUpdate(),
            triggerChartsUpdate()
        ]);
        
        // Reset game state for all players
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
    preAnsweredQuestions, //  Expose pre-answered questions
    preReadyPlayers,
    isWaitingToStart,
    
    // Methods
    fetchPlayers,
    changePlayerCount,
    answerQuestion, // ENHANCED: Now handles auto-progression
    submitAnswers,
    resetGameState,
    addFlashMessage,
    removeFlashMessage,
    clearFlashMessages,
    registerCallbacks,
    setAIModule, //  Expose setAIModule function
    
    // Cleanup
    cleanup
  };
}