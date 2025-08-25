// resources/js/Composables/useBespokeAI.js

import { ref, computed } from 'vue';
import axios from 'axios';

// Factory function that creates Bespoke AI composable with dependencies
export function createBespokeAI(gameId, getDependencies) {
  // Reactive state
  const bespokeAIAnswers = ref({});
  const bespokeAILoading = ref(false);
  const bespokeAIError = ref(null);
  const playWithBespokeAI = ref(false);
  const selectedAIModel = ref(null);
  const availableModels = ref([]);
  const modelStats = ref({});

  // Computed property that gets dependencies when needed
  const allPlayersAnswered = computed(() => {
    if (!playWithBespokeAI.value) return false;
    
    const deps = getDependencies();
    if (!deps.gameState || !deps.players) return false;
    
    const currentQuestion = deps.currentQuestionIndex;
    const totalPlayers = deps.players.length;
    
    // SAFETY CHECK: Ensure gameState.playersAnswered exists and is iterable
    if (!deps.gameState.playersAnswered) {
      console.warn('gameState.playersAnswered is not initialized yet');
      return false;
    }
    
    // Check if it's a Set (which has Symbol.iterator) or convert it to array safely
    let playersAnsweredArray;
    try {
      if (deps.gameState.playersAnswered instanceof Set) {
        playersAnsweredArray = Array.from(deps.gameState.playersAnswered);
      } else if (Array.isArray(deps.gameState.playersAnswered)) {
        playersAnsweredArray = deps.gameState.playersAnswered;
      } else {
        console.warn('playersAnswered is not a Set or Array:', typeof deps.gameState.playersAnswered);
        return false;
      }
    } catch (error) {
      console.error('Error converting playersAnswered to array:', error);
      return false;
    }
    
    // Count how many players have answered the current question
    const answeredCount = playersAnsweredArray
      .filter(key => key.endsWith(`-${currentQuestion}`))
      .length;
        
    return answeredCount >= totalPlayers && totalPlayers > 0;
  });

  // Load available AI models
  const loadAvailableModels = async () => {
    try {
      const response = await axios.get('/api/bespoke-ai/models');
      
      if (response.data.success) {
        availableModels.value = response.data.models;
        
        // Set default model if none selected
        if (!selectedAIModel.value && availableModels.value.length > 0) {
          selectedAIModel.value = availableModels.value[0].id;
        }
        
        console.log('Available bespoke AI models loaded:', availableModels.value);
      } else {
        throw new Error(response.data.message || 'Failed to load models');
      }
    } catch (error) {
      bespokeAIError.value = error.response?.data?.message || error.message || 'Failed to load AI models';
      console.error('Bespoke AI Models Error:', error);
    }
  };

  // Get bespoke AI answer for current question (kept for backward compatibility)
  const getBespokeAIAnswer = async () => {
    if (!playWithBespokeAI.value || !selectedAIModel.value || bespokeAILoading.value) return;
    
    try {
      bespokeAILoading.value = true;
      bespokeAIError.value = null;
      
      const deps = getDependencies();
      const currentQuestion = deps.currentQuestionIndex;
      
      // Get the current question text from dependencies
      const questionText = deps.currentGameQuestions && deps.currentGameQuestions[currentQuestion]
        ? deps.currentGameQuestions[currentQuestion].question
        : null;
      
      if (!questionText) {
        throw new Error('No question text available for current question index');
      }
      
      // Use the bespoke AI API endpoint
      const response = await axios.post('/api/bespoke-ai/answer', {
        gameId: gameId,
        modelId: selectedAIModel.value,
        questionIndex: currentQuestion,
        questionText: questionText,
        playerAnswer: '',
        difficultyId: deps.selectedDifficulty || null,
        categoryId: deps.selectedCategory || null
      });
      
      if (response.data.success) {
        // Store bespoke AI answer with proper structure
        bespokeAIAnswers.value[currentQuestion] = {
          answer: response.data.answer,
          score: response.data.score,
          predicted_score: response.data.predicted_score,
          isCorrect: response.data.isCorrect,
          cached: response.data.cached,
          model_id: response.data.model_id
        };
        console.log('Bespoke AI answer received:', response.data.answer);
      } else {
        throw new Error(response.data.message || 'Failed to get bespoke AI answer');
      }
      
    } catch (error) {
      bespokeAIError.value = error.response?.data?.message || error.message || 'Failed to get bespoke AI answer';
      console.error('Bespoke AI Error:', error);
    } finally {
      bespokeAILoading.value = false;
    }
  };

  // Get bespoke AI answer for a specific question index
  const getBespokeAIAnswerForQuestion = async (questionText, gameId, questionIndex, selectedDifficulty, selectedCategory, playerAnswer = '') => {
    if (!playWithBespokeAI.value || !selectedAIModel.value || bespokeAILoading.value) return;
    
    try {
      bespokeAILoading.value = true;
      bespokeAIError.value = null;
            
      // Use the bespoke AI API endpoint
      const response = await axios.post('/api/bespoke-ai/answer', {
        gameId: gameId,
        modelId: selectedAIModel.value,
        questionIndex: questionIndex,
        questionText: questionText,
        playerAnswer: playerAnswer,
        difficultyId: selectedDifficulty || null,
        categoryId: selectedCategory || null
      });

      console.log('RETURNED BESPOKE AI ANSWER: ' + JSON.stringify(response));
      
      if (response.data.success) {
        // Store bespoke AI answer with proper structure
        bespokeAIAnswers.value[questionIndex] = {
          answer: response.data.answer,
          score: response.data.score,
          predicted_score: response.data.predicted_score,
          isCorrect: response.data.isCorrect,
          cached: response.data.cached,
          model_id: response.data.model_id
        };
        return response.data.answer;
      } else {
        throw new Error(response.data.message || 'Failed to get bespoke AI answer');
      }
      
    } catch (error) {
      bespokeAIError.value = error.response?.data?.message || error.message || 'Failed to get bespoke AI answer';
      console.error('Bespoke AI Error for question', questionIndex, ':', error);
      return null;
    } finally {
      bespokeAILoading.value = false;
    }
  };

  // Handle steal functionality
  const handleSteal = async (targetPlayerId, questionIndex) => {
    if (!playWithBespokeAI.value || !selectedAIModel.value) return;

    try {
      bespokeAILoading.value = true;
      bespokeAIError.value = null;

      const response = await axios.post(`/api/games/${gameId}/bespoke-ai/steal`, {
        targetPlayerId: targetPlayerId,
        questionIndex: questionIndex,
        modelId: selectedAIModel.value
      });

      if (response.data.success) {
        console.log('Steal successful:', response.data.message);
        return {
          success: true,
          message: response.data.message,
          stolenAnswer: response.data.stolenAnswer || null
        };
      } else {
        throw new Error(response.data.message || 'Steal failed');
      }

    } catch (error) {
      bespokeAIError.value = error.response?.data?.message || error.message || 'Failed to execute steal';
      console.error('Bespoke AI Steal Error:', error);
      return {
        success: false,
        message: bespokeAIError.value
      };
    } finally {
      bespokeAILoading.value = false;
    }
  };

  // Get model performance statistics
  const getModelStats = async (modelId = null) => {
    try {
      const targetModelId = modelId || selectedAIModel.value;
      if (!targetModelId) return;

      const response = await axios.get(`/api/games/${gameId}/bespoke-ai/stats/${targetModelId}`);
      
      if (response.data.success) {
        modelStats.value[targetModelId] = response.data.stats;
      }
    } catch (error) {
      console.error('Failed to load model stats:', error);
    }
  };

  // Check if bespoke AI has answered a specific question
  const hasBespokeAIAnswered = (questionIndex) => {
    const answer = bespokeAIAnswers.value[questionIndex];
    return answer && answer.answer !== undefined && answer.answer !== null && answer.answer !== '';
  };

  // Reset bespoke AI state (for new games)
  const resetBespokeAI = () => {
    bespokeAIAnswers.value = {};
    bespokeAIError.value = null;
    bespokeAILoading.value = false;
    modelStats.value = {};
    console.log('Bespoke AI state reset');
  };

  // Change selected AI model
  const changeAIModel = async (modelId) => {
    selectedAIModel.value = modelId;
    await getModelStats(modelId);
    console.log('Selected AI model changed to:', modelId);
  };

  return {
    // State
    bespokeAIAnswers,
    bespokeAILoading,
    bespokeAIError,
    playWithBespokeAI,
    selectedAIModel,
    availableModels,
    modelStats,
    
    // Computed
    allPlayersAnswered,
    
    // Methods
    loadAvailableModels,
    getBespokeAIAnswer,
    getBespokeAIAnswerForQuestion,
    handleSteal,
    getModelStats,
    hasBespokeAIAnswered,
    resetBespokeAI,
    changeAIModel
  };
}

// Convenience wrapper that matches original API
export function useBespokeAI(gameId, gameQuestions, gameState, players, currentQuestionIndex) {
  return createBespokeAI(gameId, () => ({
    gameState: gameState?.value || gameState,
    players: players?.value || players,
    currentQuestionIndex: currentQuestionIndex?.value || currentQuestionIndex,
    currentGameQuestions: gameQuestions?.value || gameQuestions
  }));
}