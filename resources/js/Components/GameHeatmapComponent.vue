<script setup>
import { ref, defineProps, onMounted, watch } from 'vue';
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

// Fetch all game scores for heatmap
const fetchAllGameScores = async () => {
  try {
    const response = await axios.get(`/games/${props.gameId}/all-scores`);
    allGameScores.value = response.data;
    calculateQuestionTotals();
  } catch (error) {
    console.error('Error fetching all game scores:', error);
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
        answers = JSON.parse(answers);
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
  const grouped = {};
  const maxScoresByQuestion = {};
  const userAttempts = {}; // Track attempts per user, not per question
  const questionCorrectCounts = {}; // Track correct answers per user per question

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

    // Increment user attempts (each score record = one game submission)
    userAttempts[playerName]++;

    let answers = score.answer_json;
    if (!answers) return;

    if (typeof answers === 'string') {
      try {
        answers = JSON.parse(answers);
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

      // Fallback: if we don't have max score from props, use the score_awarded from the answer
      if (!maxScoresByQuestion[label] && answer?.score_awarded) {
        maxScoresByQuestion[label] = answer.score_awarded;
      }

      // Track correct answers per user per question
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
            y: successRate, // Use success rate for coloring instead of average score
            avgScore: count > 0 ? totalPlayerScore / count : 0, // Keep average for tooltip
            totalScore: maxScoresByQuestion[label] || 0,
            playerTotalScore: totalPlayerScore,
            attempts: playerAttempts, // Now correctly shows user's total game attempts
            successRate: successRate, // Success rate for this specific question
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
        type: 'none', // normal state no filter
      }
    },
    hover: {
      filter: {
        type: 'none',
      }
    },
    active: {
      filter: {
        type: 'none' // disable any active state color change on click
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
          // Much darker base color for lowest range for better text readability
          { from: 0, to: 20, color: '#1a346e' }, // Even darker blue
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
    labels: { style: { colors: '#e5e7eb' } },
  },
  yaxis: {
    labels: { offsetX: 10, style: { colors: '#e5e7eb' } },
  },
});

// Watch for gameId changes
watch(
  () => props.gameId,
  async () => {
    await fetchAllGameScores();
  }
);

// Initial data fetch
onMounted(async () => {
  await fetchAllGameScores();
});

// Expose method to refresh heatmap externally
defineExpose({
  refreshHeatmap: async () => {
    await fetchAllGameScores();
  },
});
</script>

<template>
  <div class="p-4 h-full bg-gray-800 rounded shadow text-gray-200 flex flex-col">
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
        No score data available yet
      </div>
    </div>
  </div>
</template>

<style scoped>
.chart-container :deep(.apexcharts-canvas) {
  min-height: 229px !important;
}
</style>