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

// Filter states - will be updated by event listener
const dateRange = ref([null, null]);
const userIds = ref([]);
const andUsers = ref(false);
const excludeAI = ref(true);

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

    const url = `/games/${props.gameId}/all-scores${params.toString() ? '?' + params.toString() : ''}`;
    const response = await axios.get(url);
    
    allGameScores.value = response.data;
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

// Heatmap series data
const getQuestionAveragesByUser = () => {
  if (allGameScores.value.length === 0) {
    return [];
  }

  const grouped = {};
  const maxScoresByQuestion = {};
  const userAttempts = {};
  const questionCorrectCounts = {};

  // Build max scores from props.gameQuestions first
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

      if (!maxScoresByQuestion[label] && answer?.score_awarded) {
        maxScoresByQuestion[label] = answer.score_awarded;
      }

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
          
          return {
            x: label,
            y: successRate,
            avgScore: count > 0 ? totalPlayerScore / count : 0,
            totalScore: maxScoresByQuestion[label] || 0,
            playerTotalScore: totalPlayerScore,
            attempts: playerAttempts,
            successRate: successRate,
          };
        }),
    };
  });
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
      const successRate = series[seriesIndex][dataPointIndex];
      const dataPoint = w.globals.initialSeries[seriesIndex].data[dataPointIndex];
      const attempts = dataPoint?.attempts ?? 1;
      const avgScore = dataPoint?.avgScore ?? 0;

      return `<div style="padding:8px; background-color:#1e1e1e; color:#cccccc; border-radius:4px;">
        <strong>${player}</strong><br/>
        <strong>${question}</strong><br/>
        Average Score: <strong>${avgScore.toFixed(2)}</strong><br/>
        No. Attempts: <strong>${attempts}</strong><br/>
        Success Rate: <strong>${successRate.toFixed(0)}%</strong><br/>
      </div>`;
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
      
      return `${formattedAvgScore} / ${totalScore}`;
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
});

// Listen for filter changes from GameAuthenticated layout
const handleFilterChange = (event) => {
  const { dateRange: newDateRange, userIds: newUserIds, andUsers: newAndUsers, excludeAI: newExcludeAI } = event.detail;
  
  dateRange.value = newDateRange;
  userIds.value = newUserIds;
  andUsers.value = newAndUsers;
  excludeAI.value = newExcludeAI !== undefined ? newExcludeAI : true;
  
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