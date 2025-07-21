import { ref, computed, watch } from 'vue';
import axios from 'axios';

export function useAI(gameId, gameQuestions, gameState, players, currentQuestionIndex) {
  // Reactive state
  const aiAnswers = ref([]);
  const aiLoading = ref(false);
  const aiError = ref(null);
  const playWithAI = ref(false);

  // FIXED: Computed property to check if all players have answered the current question
  const allPlayersAnswered = computed(() => {
    if (!playWithAI.value) return false;
    
    const currentQuestion = currentQuestionIndex.value;
    const totalPlayers = players.value.length;
    
    // SAFETY CHECK: Ensure gameState.value.playersAnswered exists and is iterable
    if (!gameState.value || !gameState.value.playersAnswered) {
      console.warn('gameState.playersAnswered is not initialized yet');
      return false;
    }
    
    // Check if it's a Set (which has Symbol.iterator) or convert it to array safely
    let playersAnsweredArray;
    try {
      if (gameState.value.playersAnswered instanceof Set) {
        playersAnsweredArray = Array.from(gameState.value.playersAnswered);
      } else if (Array.isArray(gameState.value.playersAnswered)) {
        playersAnsweredArray = gameState.value.playersAnswered;
      } else {
        console.warn('playersAnswered is not a Set or Array:', typeof gameState.value.playersAnswered);
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

  // Watch for when all players have answered and AI should respond
  watch(allPlayersAnswered, async (newValue) => {
    if (newValue && playWithAI.value) {
      await getAIAnswer();
    }
  });

  // Get AI answer for current question
  const getAIAnswer = async () => {
    if (!playWithAI.value || aiLoading.value) return;
    
    try {
      aiLoading.value = true;
      aiError.value = null;
      
      const currentQuestion = currentQuestionIndex.value;
            
      // Use the correct API endpoint that matches your controller
      const response = await axios.post('/api/ai/answer', {
        gameId: gameId,
        questionIndex: currentQuestion
      });
      
      if (response.data.success) {
        // Store AI answer with proper structure
        aiAnswers.value[currentQuestion] = {
          answer: response.data.answer,
          score: response.data.score,
          isCorrect: response.data.isCorrect,
          cached: response.data.cached
        };
        console.log('AI answer received:', response.data.answer);
      } else {
        throw new Error(response.data.message || 'Failed to get AI answer');
      }
      
    } catch (error) {
      aiError.value = error.response?.data?.message || error.message || 'Failed to get AI answer';
      console.error('AI Error:', error);
    } finally {
      aiLoading.value = false;
    }
  };

  // Get AI answer for a specific question index - updated to match backend API
  const getAIAnswerForQuestion = async (questionText, gameId, questionIndex) => {
    if (!playWithAI.value || aiLoading.value) return;
    
    try {
      aiLoading.value = true;
      aiError.value = null;
            
      // Use the correct API endpoint
      const response = await axios.post('/api/ai/answer', {
        gameId: gameId,
        questionIndex: questionIndex
      });
      
      if (response.data.success) {
        // Store AI answer with proper structure
        aiAnswers.value[questionIndex] = {
          answer: response.data.answer,
          score: response.data.score,
          isCorrect: response.data.isCorrect,
          cached: response.data.cached
        };
        return response.data.answer;
      } else {
        throw new Error(response.data.message || 'Failed to get AI answer');
      }
      
    } catch (error) {
      aiError.value = error.response?.data?.message || error.message || 'Failed to get AI answer';
      console.error('AI Error for question', questionIndex, ':', error);
      return null;
    } finally {
      aiLoading.value = false;
    }
  };

  // Check if AI has answered a specific question
  const hasAIAnswered = (questionIndex) => {
    return aiAnswers.value[questionIndex] !== undefined;
  };

  // Reset AI state (for new games)
  const resetAI = () => {
    aiAnswers.value = [];
    aiError.value = null;
    aiLoading.value = false;
    console.log('AI state reset');
  };

  return {
    // State
    aiAnswers,
    aiLoading,
    aiError,
    playWithAI,
    
    // Computed
    allPlayersAnswered,
    
    // Methods
    getAIAnswer,
    getAIAnswerForQuestion,
    hasAIAnswered,
    resetAI
  };
}