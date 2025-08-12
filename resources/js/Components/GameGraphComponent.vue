<script setup>
import { ref, defineProps, onMounted, watch, onUnmounted } from 'vue';
import { Chart } from 'chart.js/auto';
import axios from 'axios';

const props = defineProps({
  gameId: {
    type: [String, Number],
    required: true,
  },
});

const chartCanvas = ref(null);
let chartInstance = null;
const playersData = ref([]);
const totalGameScore = ref(null);
const excludeAI = ref(true);
const difficultyId = ref(null);
const categoryId = ref(null);

// Filter states - will be updated by event listener
const dateRange = ref([null, null]);
const userIds = ref([]);
const andUsers = ref(false);

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

  // Difficulty & Category
  difficultyId.value = urlParams.get('difficulty');
  categoryId.value = urlParams.get('category');
};

// Fetch player averages with filters
const fetchScoreTrendStats = async () => {
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

    if (difficultyId.value) {
      params.set('difficulty', difficultyId.value);
    }
    if (categoryId.value) {
      params.set('category', categoryId.value);
    }

    const url = `/api/games/${props.gameId}/score-trends${params.toString() ? '?' + params.toString() : ''}`;
    const response = await axios.get(url);
    
    playersData.value = response.data.players;
    totalGameScore.value = response.data.totalScore;
  } catch (error) {
    console.error('Error fetching player averages:', error);
    playersData.value = [];
    totalGameScore.value = null;
  }
};

// Helper function to format count data for tooltip
const formatCountData = (player) => {
  if (!player.counts) return [];
  
  const labels = [];
  
  // Always show complete breakdown by difficulty, regardless of current filters
  const easyCounts = Object.keys(player.counts)
    .filter(key => key.startsWith('1_'))
    .reduce((sum, key) => sum + (player.counts[key] || 0), 0);
  const mediumCounts = Object.keys(player.counts)
    .filter(key => key.startsWith('2_'))
    .reduce((sum, key) => sum + (player.counts[key] || 0), 0);
  const hardCounts = Object.keys(player.counts)
    .filter(key => key.startsWith('3_'))
    .reduce((sum, key) => sum + (player.counts[key] || 0), 0);
  
  // Show total games first
  const totalGames = easyCounts + mediumCounts + hardCounts;
  if (totalGames > 0) {
    labels.push(`Total Games Played: ${totalGames}`);
    
    // Show breakdown by difficulty
    if (easyCounts > 0) labels.push(`Easy Games: ${easyCounts}`);
    if (mediumCounts > 0) labels.push(`Medium Games: ${mediumCounts}`);
    if (hardCounts > 0) labels.push(`Hard Games: ${hardCounts}`);
  }
  
  return labels;
};

// Draw or update the chart
const drawChart = () => {
  if (!chartCanvas.value) return;
  const ctx = chartCanvas.value.getContext('2d');

  if (chartInstance) {
    chartInstance.destroy();
  }
  if (playersData.value.length === 0) return;

  // Precompute success rates for each player
  const playerDataWithRates = playersData.value.map(player => {
    let maxScore = null;

    if (difficultyId.value) {
      // difficulty filter is set: pick the max for that difficulty
      const difficultyIndex = parseInt(difficultyId.value, 10) - 1;
      const maxes = [
        totalGameScore.value?.totalEasy,
        totalGameScore.value?.totalMedium,
        totalGameScore.value?.totalDifficult
      ];

      maxScore = maxes[difficultyIndex] ?? null;

      return {
        ...player,
        successRate: maxScore ? (player.average_score / maxScore) * 100 : null,
        maxScore
      };
    } else {
      // no difficulty filter: average max across all difficulties
      const maxes = [
        totalGameScore.value?.totalEasy,
        totalGameScore.value?.totalMedium,
        totalGameScore.value?.totalDifficult
      ].filter(v => v != null);

      if (maxes.length === 0) {
        return { ...player, successRate: null, maxScore: null };
      }

      maxScore = maxes.reduce((a, b) => a + b, 0) / maxes.length;

      // Calculate success rates per difficulty using actual scores in player.counts
      const difficultyIds = [1, 2, 3];
      const successRates = difficultyIds.map(diffId => {
        const maxForDiff = totalGameScore.value?.[`total${diffId === 1 ? 'Easy' : diffId === 2 ? 'Medium' : 'Difficult'}`];
        if (!maxForDiff) return null;

        // Sum player's score for this difficulty
        const playerScoreForDiff = Object.entries(player.counts || {})
          .filter(([key]) => key.startsWith(`${diffId}_`))
          .reduce((sum, [, val]) => sum + val, 0);

        if (playerScoreForDiff === 0) return null;

        return playerScoreForDiff / maxForDiff;
      }).filter(rate => rate !== null);

      const avgSuccessRate = successRates.length > 0
        ? (successRates.reduce((a, b) => a + b, 0) / successRates.length) * 100
        : null;

      return {
        ...player,
        successRate: avgSuccessRate,
        maxScore
      };
    }
  });


  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: playerDataWithRates.map(p => p.name),
      datasets: [{
        label: 'Success Rate (%)',
        data: playerDataWithRates.map(p => p.successRate),
        backgroundColor: playerDataWithRates.map(() => 'rgba(54, 162, 235, 0.5)'),
        borderColor: playerDataWithRates.map(() => 'rgba(54, 162, 235, 1)'),
        borderWidth: 1,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          min: 0,
          max: 100,
          ticks: {
            callback: value => `${value}%`,
            color: '#758096',
            padding: 5
          },
          grid: { color: 'rgba(54, 162, 235, 0.15)' }
        }
      },
      plugins: {
        legend: { labels: { color: '#758096' } },
        tooltip: {
          callbacks: {
            label: function (context) {
              const player = playerDataWithRates[context.dataIndex];
              const avg = player.average_score !== undefined && player.average_score !== null
                ? Number(player.average_score).toFixed(2)
                : 'N/A';

              const labels = [];

              // If no diff/cat filter → show overall success rate
              if (!difficultyId.value && !categoryId.value) {
                labels.push(`Overall Success Rate: ${player.successRate?.toFixed(2) ?? 'N/A'}%`);
              } else {
                labels.push(`Max Game Score: ${totalGameScore.value?.totalScore ?? 'N/A'}`);
                labels.push(`Average Score: ${avg}`);
                if (player.maxScore) {
                  labels.push(`Success Rate: ${player.successRate?.toFixed(2) ?? 'N/A'}%`);
                }
              }

              // Count data breakdown
              const countLabels = formatCountData(player);
              if (countLabels.length > 0) {
                labels.push('', ...countLabels);
              }

              return labels;
            }
          }
        }
      }
    },
  });
};



// Update handleFilterChange function
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
  difficultyId.value = newDifficultyId; 
  categoryId.value = newCategoryId;     
  
  // Refresh data and chart
  fetchScoreTrendStats().then(() => {
    drawChart();
  });
};

// Watch for gameId changes
watch(
  () => props.gameId,
  async () => {
    await fetchScoreTrendStats();
    drawChart();
  }
);

// Initial chart render
onMounted(async () => {
  getInitialFilters();
  
  // Add event listener for filter changes
  window.addEventListener('gameFiltersChanged', handleFilterChange);
  
  await fetchScoreTrendStats();
  drawChart();
});

onUnmounted(() => {
  // Clean up event listener
  window.removeEventListener('gameFiltersChanged', handleFilterChange);
  
  if (chartInstance) {
    chartInstance.destroy();
  }
});

// Expose method to refresh chart externally
defineExpose({
  refreshChart: async () => {
    await fetchScoreTrendStats();
    drawChart();
  },
});
</script>

<template>
  <div class="p-4 bg-gray-800 rounded shadow flex flex-col">
    <h3 class="font-semibold text-lg mb-2">Score Trends</h3>
    <div class="flex-1 flex items-center justify-center">
      <div v-if="playersData.length === 0" class="text-center text-gray-400">
        No player data available for the selected filters.
      </div>
      <canvas
        v-else
        ref="chartCanvas"
        class="w-full h-full"
        role="img"
        aria-label="Bar chart showing average player scores"
      />
    </div>
  </div>
</template>