<script setup>
import { ref, defineProps, onMounted, watch } from 'vue';
import VueApexCharts from "vue3-apexcharts";
import axios from 'axios';

const props = defineProps({
  // Removed gameId as the heatmap will now be filtered by gameTypeId, startDate, etc.
  gameQuestions: { // Still useful for max scores
    type: Array,
    default: () => [],
  },
  gameTypeId: { // New prop for filtering
    type: [String, Number],
    default: null
  },
  startDate: { // New prop for filtering
    type: [Date, null],
    default: null
  },
  endDate: { // New prop for filtering
    type: [Date, null],
    default: null
  },
  userId: { // New prop for filtering
    type: [String, Number],
    default: null
  }
});

// Reactive state
const allGameScores = ref([]);
const questionTotals = ref({});

/**
 * Fetches all game scores for the heatmap, applying the dashboard filters.
 */
const fetchCumulativeHeatmapScores = async () => {
  try {
    const params = {};
    if (props.gameTypeId !== null) params.game_type_id = props.gameTypeId;
    if (props.startDate) params.start_date = props.startDate.toISOString().split('T')[0];
    if (props.endDate) params.end_date = props.endDate.toISOString().split('T')[0];
    if (props.userId !== null) params.user_id = props.userId;

    const response = await axios.get(`/dashboard/cumulative-heatmap`, { params });
    allGameScores.value = response.data;
    calculateQuestionTotals(); // Recalculate totals based on new data
  } catch (error) {
    console.error('Error fetching all game scores for heatmap:', error);
    allGameScores.value = []; // Clear data on error
  }
};

/**
 * Calculates the total scores for each question across all fetched game scores.
 * This function is primarily for internal use, though the heatmap uses success rate.
 */
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

/**
 * Prepares the heatmap series data, calculating success rates per player per question.
 * Success rate is defined as (number of correct answers / total attempts by user in filtered data) * 100.
 */
const getQuestionAveragesByUser = () => {
  const grouped = {}; // Stores aggregated score data for each player per question
  const maxScoresByQuestion = {}; // Stores maximum possible score for each question
  const userAttempts = {}; // Tracks total game attempts for each user within the filtered dataset
  const questionCorrectCounts = {}; // Tracks the number of correct answers for each user per question

  // Populate maxScoresByQuestion from props.gameQuestions (if available)
  // This helps ensure questions that weren't answered in the filtered data still have a max score reference
  props.gameQuestions?.forEach(question => {
    const questionNumber = question.question_number || question.id;
    const label = `Q${questionNumber}`;
    maxScoresByQuestion[label] = question.score_awarded || 0;
  });

  // Process all fetched game scores
  allGameScores.value.forEach(score => {
    const playerName = score.player_name || 'Anonymous'; // Use player_name from backend
    const playerId = score.player_id;

    if (!grouped[playerId]) {
      grouped[playerId] = { name: playerName, questions: {} };
      userAttempts[playerId] = 0;
      questionCorrectCounts[playerId] = {};
    }

    // Increment user's total game attempts for the filtered period
    userAttempts[playerId]++;

    let answers = score.answer_json;
    if (!answers) return;

    // Parse answer_json if it's a string
    if (typeof answers === 'string') {
      try {
        answers = JSON.parse(answers);
      } catch {
        return;
      }
    }

    // Iterate through each answer in the current game score
    Object.entries(answers).forEach(([_, answer]) => {
      const questionNumber = answer?.question_number;
      const scoreValue = answer?.score_awarded ?? 0;
      const isCorrect = answer?.is_correct ?? false;

      if (!questionNumber) return;

      const label = `Q${questionNumber}`;

      // Fallback: if max score isn't from props, use score_awarded from the answer
      if (!maxScoresByQuestion[label] && answer?.score_awarded) {
        maxScoresByQuestion[label] = answer.score_awarded;
      }

      // Initialize and increment correct answer count for the specific user and question
      if (!questionCorrectCounts[playerId][label]) {
        questionCorrectCounts[playerId][label] = 0;
      }
      if (isCorrect) {
        questionCorrectCounts[playerId][label]++;
      }

      // Initialize and aggregate total score and count for average calculation
      if (!grouped[playerId].questions[label]) {
        grouped[playerId].questions[label] = { totalPlayerScore: 0, count: 0 };
      }

      grouped[playerId].questions[label].totalPlayerScore += scoreValue;
      grouped[playerId].questions[label].count++;
    });
  });

  // Transform grouped data into ApexCharts heatmap series format
  return Object.entries(grouped).map(([playerId, playerInfo]) => {
    const playerName = playerInfo.name;
    const playerTotalAttempts = userAttempts[playerId] || 0; // Total attempts by this player

    return {
      name: playerName,
      data: Object.entries(playerInfo.questions)
        // Sort questions numerically (e.g., Q1, Q2, Q10)
        .sort(([a], [b]) => parseInt(a.replace('Q', '')) - parseInt(b.replace('Q', '')))
        .map(([label, { totalPlayerScore, count }]) => {
          const correctCount = questionCorrectCounts[playerId][label] || 0;
          // Calculate success rate: correct answers for this question / total games played by user
          const successRate = playerTotalAttempts > 0 ? (correctCount / playerTotalAttempts) * 100 : 0;
          
          return {
            x: label, // Question label (e.g., Q1)
            y: successRate, // Value for coloring the heatmap cell (success rate)
            avgScore: count > 0 ? totalPlayerScore / count : 0, // Average score for this question (for tooltip)
            totalScore: maxScoresByQuestion[label] || 0, // Max possible score for this question
            playerTotalScore: totalPlayerScore, // Total score awarded to player for this question
            attempts: playerTotalAttempts, // Total games played by this user
            correctAnswers: correctCount, // Number of times this question was answered correctly by this user
          };
        }),
    };
  });
};

// Heatmap chart options
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
      const dataPoint = w.globals.initialSeries[seriesIndex].data[dataPointIndex];
      
      const successRate = dataPoint?.y ?? 0; // The 'y' value is the success rate
      const avgScore = dataPoint?.avgScore ?? 0;
      const totalScorePossible = dataPoint?.totalScore ?? 0;
      const totalAttemptsByUser = dataPoint?.attempts ?? 0;
      const correctAnswersForQuestion = dataPoint?.correctAnswers ?? 0;

      return `<div style="padding:8px; background-color:#1e1e1e; color:#cccccc; border-radius:4px;">
        <strong>${player}</strong><br/>
        <strong>${question}</strong><br/>
        Average Score for Question: <strong>${avgScore.toFixed(2)} / ${totalScorePossible}</strong><br/>
        Correct Answers: <strong>${correctAnswersForQuestion}</strong><br/>
        Total Games Played by User: <strong>${totalAttemptsByUser}</strong><br/>
        Success Rate for Question: <strong>${successRate.toFixed(0)}%</strong><br/>
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
          { from: 0, to: 20, color: '#1a346e', name: '0-20% Success' }, // Even darker blue
          { from: 21, to: 40, color: '#3b82f6', name: '21-40% Success' },
          { from: 41, to: 60, color: '#60a5fa', name: '41-60% Success' },
          { from: 61, to: 80, color: '#1e40af', name: '61-80% Success' },
          { from: 81, to: 100, color: '#1e3a8a', name: '81-100% Success' },
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
      colors: ['#fff'] // Ensure data labels are white for readability
    },
  },
  grid: {
    padding: { right: 20, left: 30, top: 0, bottom: 0 }
  },
  legend: { show: false }, // Legend is not very useful for this heatmap color scale
  colors: ['#33a6cc'], // This is a fallback, ranges in plotOptions take precedence
  xaxis: {
    labels: { style: { colors: '#758096' } },
  },
  yaxis: {
    labels: { offsetX: 10, style: { colors: '#758096' } },
  },
});

// Watch for changes in filter props and refetch data
watch(
  [() => props.gameTypeId, () => props.startDate, () => props.endDate, () => props.userId],
  async () => {
    await fetchCumulativeHeatmapScores();
  }
);

// Initial data fetch on component mount
onMounted(async () => {
  await fetchCumulativeHeatmapScores();
});

// Expose method to refresh heatmap externally
defineExpose({
  refreshHeatmap: async () => {
    await fetchCumulativeHeatmapScores();
  },
});
</script>

<template>
  <div class="p-4 h-full bg-gray-800 rounded shadow flex flex-col">
    <h3 class="font-semibold text-lg mb-2 self-start">Player Question Success Rate Heatmap</h3>
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
        No score data available for the selected filters.
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Ensure chart container respects its min-height */
.chart-container :deep(.apexcharts-canvas) {
  min-height: 229px !important;
}
</style>

