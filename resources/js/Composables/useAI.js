import { ref, computed } from 'vue';
import axios from 'axios';

// Factory function that creates AI composable with dependencies
export function createAI(gameId, getDependencies) {
  // Reactive state
  const aiAnswers = ref({});
  const aiLoading = ref(false);
  const aiError = ref(null);
  const playWithAI = ref(false);

  // Computed property that gets dependencies when needed
  const allPlayersAnswered = computed(() => {
    if (!playWithAI.value) return false;
    
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

  // Get AI answer for current question (kept for backward compatibility)
  const getAIAnswer = async () => {
    if (!playWithAI.value || aiLoading.value) return;
    
    try {
      aiLoading.value = true;
      aiError.value = null;
      
      const deps = getDependencies();
      const currentQuestion = deps.currentQuestionIndex;
      
      // Get the current question text from dependencies
      const questionText = deps.currentGameQuestions && deps.currentGameQuestions[currentQuestion]
        ? deps.currentGameQuestions[currentQuestion].question
        : null;
      
      if (!questionText) {
        throw new Error('No question text available for current question index');
      }
      
      // Use the correct API endpoint that includes the question text
      const response = await axios.post('/api/ai/answer', {
        gameId: gameId,
        questionIndex: currentQuestion,
        questionText: questionText, // Include the actual question text
        difficultyId: deps.selectedDifficulty || null,
        categoryId: deps.selectedCategory || null
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

  // Get AI answer for a specific question index
  const getAIAnswerForQuestion = async (questionText, gameId, questionIndex, selectedDifficulty, selectedCategory) => {
    if (!playWithAI.value || aiLoading.value) return;
    
    try {
      aiLoading.value = true;
      aiError.value = null;
            
      // Use the correct API endpoint with the question text
      const response = await axios.post('/api/ai/answer', {
        gameId: gameId,
        questionIndex: questionIndex,
        questionText: questionText, // Actually use the question text parameter
        difficultyId: selectedDifficulty || null,
        categoryId: selectedCategory || null
      });

      console.log('ETURNED AI ANSWER: ' + JSON.stringify(response));
      
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
    const answer = aiAnswers.value[questionIndex];
    return answer && answer.answer !== undefined && answer.answer !== null && answer.answer !== '';
  };

  // Reset AI state (for new games)
  const resetAI = () => {
    aiAnswers.value = {}; // Reset to empty object
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

// Convenience wrapper that matches original API
export function useAI(gameId, gameQuestions, gameState, players, currentQuestionIndex) {
  return createAI(gameId, () => ({
    gameState: gameState?.value || gameState,
    players: players?.value || players,
    currentQuestionIndex: currentQuestionIndex?.value || currentQuestionIndex,
    currentGameQuestions: gameQuestions?.value || gameQuestions
  }));
}