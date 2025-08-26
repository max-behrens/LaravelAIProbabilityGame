<script setup>
import { ref, defineProps, onMounted, watch, onUnmounted } from 'vue';
import VueApexCharts from "vue3-apexcharts";
import axios from 'axios';

const props = defineProps({
  gameId: {
    type: [String, Number],
    required: true,
  },
  gameQuestions: {
    type: Array,
    default: () => [],
  },
});



// Reactive state
const allGameScores = ref([]);
const questionTotals = ref({});
const totalGameScore = ref(null);

// Filter states - will be updated by event listener
const dateRange = ref([null, null]);
const userIds = ref([]);
const andUsers = ref(false);
const excludeAI = ref(false);
const difficultyId = ref(null);
const categoryId = ref(null);

// Get initial filter values from URL
const getInitialFilters = () => {
  const urlParams = new URLSearchParams(window.location.search);
  
  // Date range
  const startDate = urlParams.get('start_date');
  const endDate = urlParams.get('end_date');
  if (startDate && endDate) {
    dateRange.value = [new Date(startDate), new Date(endDate)];
  }
  
  // User IDs
  const userIdsParam = urlParams.get('user_ids');
  if (userIdsParam) {
    userIds.value = userIdsParam.split(',').map(id => parseInt(id)).filter(id => !isNaN(id));
  }
  
  // AND users
  andUsers.value = urlParams.get('and_users') === 'true';

  difficultyId.value = urlParams.get('difficulty');
  categoryId.value = urlParams.get('category');
};

// Fetch all game scores for heatmap with filters
const fetchAllGameScores = async () => {
  try {
    
    const params = new URLSearchParams();

    
    // Add date range
    if (dateRange.value[0] && dateRange.value[1]) {
      params.set('start_date', dateRange.value[0].toISOString().split('T')[0]);
      params.set('end_date', dateRange.value[1].toISOString().split('T')[0]);
    }
    
    // Add user IDs
    if (userIds.value.length > 0) {
      params.set('user_ids', userIds.value.join(','));
      params.set('and_users', andUsers.value.toString());
    }

    // Add exclude AI parameter - defaults to true
    params.set('exclude_ai', excludeAI.value.toString());

    // Only add filters if they have valid values
    if (difficultyId.value !== null && difficultyId.value !== '') {
      params.set('difficulty', difficultyId.value);
    }
    if (categoryId.value !== null && categoryId.value !== '') {
      params.set('category', categoryId.value);
    }

    console.log('HERE Q FETCH')

    const url = `/games/${props.gameId}/game-heatmap-scores${params.toString() ? '?' + params.toString() : ''}`;
    const response = await axios.get(url);
    
    allGameScores.value = response.data.allScores;
    totalGameScore.value = response.data.totalScore;
    calculateQuestionTotals();
  } catch (error) {
    console.error('Error fetching all game scores:', error);
    allGameScores.value = [];
  }
};

// Calculate question totals
const calculateQuestionTotals = () => {
  const totals = {};
  allGameScores.value.forEach(score => {
    let answers = score.answer_json;
    if (!answers) return;
    
    if (typeof answers === 'string') {
      try {
        while (typeof answers === 'string') {
          answers = JSON.parse(answers);
        }
      } catch {
        return;
      }
    }

    Object.values(answers).forEach(answer => {
      const qNum = answer?.question_number;
      const s = answer?.score_awarded ?? 0;
      if (!qNum) return;
      const label = `Q${qNum}`;
      totals[label] = (totals[label] || 0) + s;
    });
  });

  questionTotals.value = totals;
};

// Get total score for a specific difficulty (per question)
const getTotalScoreForDifficulty = (difficulty) => {
  if (!totalGameScore.value) return 0;
  
  const difficultyMap = {
    1: totalGameScore.value.totalEasy,
    2: totalGameScore.value.totalMedium,
    3: totalGameScore.value.totalDifficult
  };
  
  const totalForDifficulty = difficultyMap[difficulty] || 0;
  
  // Divide by number of questions to get per-question total
  const questionCount = getQuestionCount();
  return questionCount > 0 ? totalForDifficulty / questionCount : 0;
};

// Get the number of questions in the game
const getQuestionCount = () => {
  
  // Use props.gameQuestions if available
  if (props.gameQuestions && props.gameQuestions.length > 0) {
    return props.gameQuestions.length;
  }
  
  // Fallback: count unique questions from allGameScores
  const questionNumbers = new Set();
  allGameScores.value.forEach(score => {
    let answers = score.answer_json;
    if (!answers) return;
    
    if (typeof answers === 'string') {
      try {
        while (typeof answers === 'string') {
          answers = JSON.parse(answers);
        }
      } catch {
        return;
      }
    }

    Object.values(answers).forEach(answer => {
      const qNum = answer?.question_number;
      if (qNum) {
        questionNumbers.add(qNum);
      }
    });
  });
  
  return questionNumbers.size || 1; // Default to 1 to avoid division by zero
};

// Get attempt counts for a player filtered by difficulty and category
const getPlayerAttemptCounts = (playerName) => {
  console.log('Player Name: ' + JSON.stringify(playerName));
  const counts = { 1: 0, 2: 0, 3: 0 }; // Easy, Medium, Hard
  
  allGameScores.value.forEach(score => {
    const scorePlayerName = score.user?.name || 'Anonymous';
    if (scorePlayerName !== playerName) return;

    let answers = score.answer_json;
    if (!answers) return;

    if (typeof answers === 'string') {
      try {
        while (typeof answers === 'string') {
          answers = JSON.parse(answers);
        }
      } catch {
        return;
      }
    }

    // Get difficulty and category from answer_json
    const diffId = answers?.difficulty_id;
    const catId = answers?.category_id;
    
    if (!diffId) return;

    // Apply category filter if set
    if (categoryId.value !== null && catId != categoryId.value) {
      return;
    }

    counts[diffId] = (counts[diffId] || 0) + 1;
  });

  return counts;
};

// Heatmap series data
const getQuestionAveragesByUser = () => {
  if (allGameScores.value.length === 0) {
    return [];
  }

  const grouped = {};
  const maxScoresByQuestion = {};
  const userAttempts = {};
  const questionCorrectCounts = {};

  // Build max scores from props.gameQuestions first (per question)
  props.gameQuestions?.forEach(question => {
    const questionNumber = question.question_number || question.id;
    const label = `Q${questionNumber}`;
    maxScoresByQuestion[label] = question.score_awarded || 0;
  });

  allGameScores.value.forEach(score => {
    const playerName = score.user?.name || 'Anonymous';
    if (!grouped[playerName]) {
      grouped[playerName] = {};
      userAttempts[playerName] = 0;
      questionCorrectCounts[playerName] = {};
    }

    userAttempts[playerName]++;

    let answers = score.answer_json;
    if (!answers) return;

    if (typeof answers === 'string') {
      try {
        while (typeof answers === 'string') {
          answers = JSON.parse(answers);
        }
      } catch {
        return;
      }
    }

    Object.entries(answers).forEach(([_, answer]) => {
      const questionNumber = answer?.question_number;
      const scoreValue = answer?.score_awarded ?? 0;
      const isCorrect = answer?.is_correct ?? false;

      if (!questionNumber) return;

      const label = `Q${questionNumber}`;

      if (!questionCorrectCounts[playerName][label]) {
        questionCorrectCounts[playerName][label] = 0;
      }
      if (isCorrect) {
        questionCorrectCounts[playerName][label]++;
      }

      if (!grouped[playerName][label]) {
        grouped[playerName][label] = { totalPlayerScore: 0, count: 0 };
      }

      grouped[playerName][label].totalPlayerScore += scoreValue;
      grouped[playerName][label].count++;
    });
  });

  // Build series
  return Object.entries(grouped).map(([playerName, questions]) => {
    const playerAttempts = userAttempts[playerName] || 0;  
        
    return {
      name: playerName,
      data: Object.entries(questions)
        .sort(([a], [b]) => parseInt(a.replace('Q', '')) - parseInt(b.replace('Q', '')))
        .map(([label, { totalPlayerScore, count }]) => {
          const correctCount = questionCorrectCounts[playerName][label] || 0;
          const successRate = playerAttempts > 0 ? (correctCount / playerAttempts) * 100 : 0;
          const avgScore = count > 0 ? totalPlayerScore / count : 0;
          
          // Get the appropriate total score per question based on difficulty filter
          let totalScore = maxScoresByQuestion[label] || 0;
          if (difficultyId.value !== null) {
            totalScore = getTotalScoreForDifficulty(parseInt(difficultyId.value));
          }
          
          
          return {
            x: label,
            y: successRate, // Show success rate only when difficulty filter is active
            avgScore: avgScore,
            totalScore: totalScore,
            playerTotalScore: totalPlayerScore,
            attempts: playerAttempts,
            successRate: successRate,
            attemptCounts: getPlayerAttemptCounts(playerName),
          };
        }),
    };
  });
};

// Get difficulty and category info for tooltip
const getDifficultyAndCategoryInfo = () => {
  if (allGameScores.value.length === 0) {
    return { difficultyInfo: 'N/A', categoryInfo: 'N/A' };
  }

  // Check if filters are applied
  const hasFilters = difficultyId.value !== null;
  
  if (!hasFilters) {
    // No filters applied, don't show difficulty/category in tooltip
    return null;
  }

  // Get difficulty/category from the most recent score when filters are applied
  const recentScore = allGameScores.value[0];
  let difficultyInfo = 'N/A';
  let categoryInfo = 'N/A';
  
  if (recentScore && recentScore.answer_json) {
    let answerData = recentScore.answer_json;
    if (typeof answerData === 'string') {
      try {
        answerData = JSON.parse(answerData);
      } catch (e) {
        console.warn('Could not parse answer_json:', e);
      }
    }
    difficultyInfo = answerData?.difficulty_id || 'N/A';
    categoryInfo = answerData?.category_id || 'N/A';
  }

  return { difficultyInfo, categoryInfo };
};

// Chart Options
const chartOptions = ref({
  chart: {
    type: 'heatmap',
    height: 350,
    minHeight: 229,
    foreColor: '#ccc',
    toolbar: {
      show: false
    },
  },
  states: {
    normal: {
      filter: {
        type: 'none',
      }
    },
    hover: {
      filter: {
        type: 'none',
      }
    },
    active: {
      filter: {
        type: 'none'
      }
    }
  },
  tooltip: {
    custom({ series, seriesIndex, dataPointIndex, w }) {
      const player = w.globals.seriesNames[seriesIndex];
      const question = w.globals.labels[dataPointIndex];
      const yValue = series[seriesIndex][dataPointIndex];
      const dataPoint = w.globals.initialSeries[seriesIndex].data[dataPointIndex];
      const attempts = dataPoint?.attempts ?? 1;
      const avgScore = dataPoint?.avgScore ?? 0;
      const successRate = dataPoint?.successRate ?? 0;
      const totalScore = dataPoint?.totalScore ?? 0;
      const attemptCounts = dataPoint?.attemptCounts || { 1: 0, 2: 0, 3: 0 };
      
      // Get difficulty/category info only if filters are applied
      const filterInfo = getDifficultyAndCategoryInfo();
      
      let tooltipContent = `<div style="padding:8px; background-color:#1e1e1e; color:#cccccc; border-radius:4px;">
        <strong>${player}</strong><br/>
        <strong>${question}</strong><br/>
        Average Score: <strong>${avgScore.toFixed(2)}</strong><br/>`;

      // Only show success rate if difficulty filter is active
      tooltipContent += `Success Rate: <strong>${successRate.toFixed(0)}%</strong><br/>`;
      
      // Show denominator (per question total)
      if (difficultyId.value !== null) {
        tooltipContent += `Max Per Question: <strong>${totalScore.toFixed(2)}</strong><br/>`;
      }

      // Show attempt counts by difficulty (filtered by category if categoryId is set)
      let totalAttempts = attemptCounts[1] + attemptCounts[2] + attemptCounts[3];
      tooltipContent += `No. Attempts: <strong>${totalAttempts}</strong><br/>`;
      
      if (attemptCounts[1] > 0) {
        tooltipContent += `Easy: <strong>${attemptCounts[1]}</strong><br/>`;
      }
      if (attemptCounts[2] > 0) {
        tooltipContent += `Medium: <strong>${attemptCounts[2]}</strong><br/>`;
      }
      if (attemptCounts[3] > 0) {
        tooltipContent += `Hard: <strong>${attemptCounts[3]}</strong><br/>`;
      }

      // Only add difficulty/category info if filters are applied
      if (filterInfo) {
        tooltipContent += `Difficulty: <strong>${filterInfo.difficultyInfo}</strong><br/>
        Category: <strong>${filterInfo.categoryInfo}</strong><br/>`;
      }

      tooltipContent += `</div>`;

      return tooltipContent;
    }
  },
  plotOptions: {
    heatmap: {
      enableShades: true,
      distributed: false,
      colorScale: {
        ranges: [
          { from: 0, to: 20, color: '#1a346e' },
          { from: 21, to: 40, color: '#3b82f6' },
          { from: 41, to: 60, color: '#60a5fa' },
          { from: 61, to: 80, color: '#1e40af' },
          { from: 81, to: 100, color: '#1e3a8a' },
        ]
      }
    }
  },
  dataLabels: {
    enabled: true,
    formatter(_, opts) {
      const dataPoint = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex];
      const avgScore = dataPoint.avgScore ?? 0;
      const totalScore = dataPoint.totalScore ?? 0;
      
      const formattedAvgScore = (avgScore % 1 === 0) 
        ? avgScore.toString() 
        : avgScore.toFixed(2);
      
      // Show denominator as per-question total when difficulty filter is active
      if (difficultyId.value !== null && totalScore > 0) {
        const formattedTotalScore = (totalScore % 1 === 0) 
          ? totalScore.toString() 
          : totalScore.toFixed(2);
        return `${formattedAvgScore} / ${formattedTotalScore}`;
      } else {
        return formattedAvgScore;
      }
    },
    style: {
      fontSize: '16px',
    },
  },
  grid: {
    padding: { right: 20, left: 30, top: 0, bottom: 0 }
  },
  legend: { show: false },
  colors: ['#33a6cc'],
  xaxis: {
    labels: { style: { colors: '#758096' } },
  },
  yaxis: {
    labels: { offsetX: 10, style: { colors: '#758096' } },
  },
  responsive: [
    {
      breakpoint: 768, // screens smaller than 768px (tailwind md breakpoint)
      options: {
        dataLabels: {
          enabled: false // disable numbers inside heatmap squares
        }
      }
    }
  ]
});

// Listen for filter changes from GameAuthenticated layout
const handleFilterChange = (event) => {
  const { 
    dateRange: newDateRange, 
    userIds: newUserIds, 
    andUsers: newAndUsers, 
    excludeAI: newExcludeAI,
    difficultyId: newDifficultyId, 
    categoryId: newCategoryId      
  } = event.detail;
  
  dateRange.value = newDateRange;
  userIds.value = newUserIds;
  andUsers.value = newAndUsers;
  excludeAI.value = newExcludeAI !== undefined ? newExcludeAI : true;
  
  // Only set if they have valid values, otherwise set to null
  difficultyId.value = (newDifficultyId !== undefined && newDifficultyId !== '') ? newDifficultyId : null;
  categoryId.value = (newCategoryId !== undefined && newCategoryId !== '') ? newCategoryId : null;
  
  // Refresh data
  fetchAllGameScores();
};

// Watch for gameId changes
watch(
  () => props.gameId,
  async () => {
    await fetchAllGameScores();
  }
);

// Initial data fetch
onMounted(async () => {
  getInitialFilters();
  
  // Add event listener for filter changes
  window.addEventListener('gameFiltersChanged', handleFilterChange);
  
  await fetchAllGameScores();
});

onUnmounted(() => {
  // Clean up event listener
  window.removeEventListener('gameFiltersChanged', handleFilterChange);

});

// Expose method to refresh heatmap externally
defineExpose({
  refreshHeatmap: async () => {
    await fetchAllGameScores();
  },
});
</script>

<template>
  <div class="p-4 h-full bg-gray-800 rounded shadow flex flex-col">
    <h3 class="font-semibold text-lg mb-2 self-start">Score Heatmap</h3>
    <div class="flex-1 min-h-0 w-full chart-container">
      <!-- Only render chart if there's data -->
      <VueApexCharts
        v-if="getQuestionAveragesByUser().length > 0"
        type="heatmap"
        width="100%"
        height="100%"
        :options="chartOptions"
        :series="getQuestionAveragesByUser()"
      />
      <!-- Show message when no data -->
      <div v-else class="flex items-center justify-center h-full text-gray-400">
        No score data available for the selected filters
      </div>
    </div>
  </div>
</template>

<style scoped>
.chart-container :deep(.apexcharts-canvas) {
  min-height: 229px !important;
}
</style>