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
const allGameScores = ref([]);
const totalGameScore = ref(null);
const excludeAI = ref(false);
const difficultyId = ref(null);
const categoryId = ref(null);
const isLoading = ref(false);

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

// Fetch all game data from single source
const fetchAllData = async () => {
  if (isLoading.value) return;
  
  isLoading.value = true;
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

    // Add exclude AI parameter
    params.set('exclude_ai', excludeAI.value.toString());

    // Add filters - matching heatmap component exactly
    if (difficultyId.value !== null && difficultyId.value !== '') {
      params.set('difficulty', difficultyId.value);
    }
    if (categoryId.value !== null && categoryId.value !== '') {
      params.set('category', categoryId.value);
    }

    console.log('Fetching data with params:', params.toString());
    
    // Fetch both heatmap scores and total score
    const [scoresResponse, totalResponse] = await Promise.all([
      axios.get(`/games/${props.gameId}/game-heatmap-scores${params.toString() ? '?' + params.toString() : ''}`),
      axios.get(`/api/games/${props.gameId}/score-trends${params.toString() ? '?' + params.toString() : ''}`)
    ]);
    
    allGameScores.value = scoresResponse.data.allScores || [];
    totalGameScore.value = totalResponse.data.totalScore;
    
    console.log('Loaded data:', {
      gameScores: allGameScores.value.length,
      totalScore: totalGameScore.value
    });
    
  } catch (error) {
    console.error('Error fetching data:', error);
    allGameScores.value = [];
    totalGameScore.value = null;
  } finally {
    isLoading.value = false;
  }
};

// Calculate player averages from allGameScores
const calculatePlayerAverages = () => {
  if (!allGameScores.value || allGameScores.value.length === 0) {
    return [];
  }

  const playerStats = {};
  const playerCounts = {};

  // Process all scores to calculate averages and counts
  allGameScores.value.forEach(score => {
    const playerName = score.user?.name || 'Anonymous';
    const playerScore = parseFloat(score.score) || 0;

    // Initialize player if not exists
    if (!playerStats[playerName]) {
      playerStats[playerName] = {
        name: playerName,
        totalScore: 0,
        gameCount: 0,
        scores: []
      };
      playerCounts[playerName] = { 1: 0, 2: 0, 3: 0 }; // Easy, Medium, Hard
    }

    // Add to totals
    playerStats[playerName].totalScore += playerScore;
    playerStats[playerName].gameCount++;
    playerStats[playerName].scores.push(playerScore);

    // Count by difficulty (for tooltip breakdown)
    let answers = score.answer_json;
    if (answers) {
      if (typeof answers === 'string') {
        try {
          while (typeof answers === 'string') {
            answers = JSON.parse(answers);
          }
        } catch (e) {
          console.warn('Error parsing answer_json:', e);
          return;
        }
      }

      const diffId = answers?.difficulty_id;
      const catId = answers?.category_id;
      
      if (diffId) {
        // Only count if category filter matches (or no category filter)
        if (categoryId.value === null || categoryId.value === '' || catId == categoryId.value) {
          playerCounts[playerName][diffId] = (playerCounts[playerName][diffId] || 0) + 1;
        }
      }
    }
  });

  // Convert to array and calculate averages
  return Object.values(playerStats).map(player => ({
    ...player,
    average_score: player.gameCount > 0 ? player.totalScore / player.gameCount : 0,
    attemptCounts: playerCounts[player.name]
  }));
};

// Get attempt counts for a player (for tooltip)
const getPlayerAttemptCounts = (playerName) => {
  const players = calculatePlayerAverages();
  const player = players.find(p => p.name === playerName);
  return player?.attemptCounts || { 1: 0, 2: 0, 3: 0 };
};

// Draw or update the chart
const drawChart = () => {
  if (!chartCanvas.value || isLoading.value) return;
  const ctx = chartCanvas.value.getContext('2d');

  if (chartInstance) {
    chartInstance.destroy();
  }

  const playersData = calculatePlayerAverages();
  if (playersData.length === 0) return;
 
  // Calculate success rates for each player
  const playerDataWithRates = playersData.map(player => {
    let maxScore = null;
    let successRate = null;

    if (difficultyId.value) {
      // Difficulty filter only - use specific difficulty max
      const difficultyIndex = parseInt(difficultyId.value, 10) - 1;
      const maxes = [
        totalGameScore.value?.totalEasy,
        totalGameScore.value?.totalMedium,
        totalGameScore.value?.totalDifficult
      ];
      maxScore = maxes[difficultyIndex] ?? 75;
      
    } else {
      // No difficulty filter - use weighted average or default
      const counts = player.attemptCounts;
      const totalGames = counts[1] + counts[2] + counts[3];
      
      if (totalGames > 0 && totalGameScore.value) {
        // Calculate weighted max based on games played
        const weightedMax = (
          (counts[1] * (totalGameScore.value.totalEasy || 75)) +
          (counts[2] * (totalGameScore.value.totalMedium || 75)) +
          (counts[3] * (totalGameScore.value.totalDifficult || 75))
        ) / totalGames;
        maxScore = weightedMax;
      } else {
        maxScore = 75; // Default fallback
      }
    }

    successRate = maxScore && player.average_score !== null 
      ? (player.average_score / maxScore) * 100 
      : 0;

    return {
      ...player,
      successRate,
      maxScore
    };
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
              try {
                const player = playerDataWithRates[context.dataIndex];
                
                if (!player) {
                  return ['No data available'];
                }

                const avg = player.average_score !== undefined && player.average_score !== null
                  ? Number(player.average_score).toFixed(2)
                  : 'N/A';

                const labels = [];

                // Success rate label
                const successRateLabel = (() => {
                  if (!difficultyId.value && !categoryId.value) {
                    return 'Overall Success Rate';
                  } else if (!difficultyId.value && categoryId.value) {
                    return 'Category Success Rate';
                  } else if (difficultyId.value && !categoryId.value) {
                    return 'Difficulty Success Rate';
                  } else {
                    return 'Success Rate';
                  }
                })();
                
                const successRateValue = player.successRate !== undefined && player.successRate !== null
                  ? player.successRate.toFixed(2)
                  : 'N/A';
                
                labels.push(`${successRateLabel}: ${successRateValue}%`);
                labels.push(`Average Score: ${avg}`);
                
                // Show Max Possible when difficulty filter is active
                if (difficultyId.value && player.maxScore !== undefined && player.maxScore !== null) {
                  labels.push(`Max Possible: ${Number(player.maxScore).toFixed(2)}`);
                }

                // Show attempt counts
                const attemptCounts = player.attemptCounts || { 1: 0, 2: 0, 3: 0 };
                let totalAttempts = attemptCounts[1] + attemptCounts[2] + attemptCounts[3];
                
                console.log('Tooltip - Player:', player.name, 'Counts:', attemptCounts, 'Total:', totalAttempts);
                
                if (totalAttempts > 0) {
                  labels.push(''); // Empty line for separation
                  labels.push(`Total Games Played: ${totalAttempts}`);
                  
                  // Show breakdown by difficulty
                  if (attemptCounts[1] > 0) labels.push(`Easy Games: ${attemptCounts[1]}`);
                  if (attemptCounts[2] > 0) labels.push(`Medium Games: ${attemptCounts[2]}`);
                  if (attemptCounts[3] > 0) labels.push(`Hard Games: ${attemptCounts[3]}`);
                }

                return labels;
              } catch (error) {
                console.error('Tooltip error:', error);
                return ['Error loading tooltip data'];
              }
            }
          }
        }
      }
    },
  });
};

// Handle filter changes
const handleFilterChange = async (event) => {
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
  
  console.log('Filter changed:', {
    difficulty: difficultyId.value,
    category: categoryId.value,
    excludeAI: excludeAI.value
  });
  
  try {
    await fetchAllData();
    drawChart();
  } catch (error) {
    console.error('Error refreshing data after filter change:', error);
  }
};

// Watch for gameId changes
watch(
  () => props.gameId,
  async () => {
    console.log('GameId changed, refreshing data');
    try {
      await fetchAllData();
      drawChart();
    } catch (error) {
      console.error('Error refreshing data after gameId change:', error);
    }
  }
);

// Initial setup
onMounted(async () => {
  getInitialFilters();
  
  window.addEventListener('gameFiltersChanged', handleFilterChange);
  
  console.log('Component mounted, loading initial data');
  try {
    await fetchAllData();
    drawChart();
  } catch (error) {
    console.error('Error loading initial data:', error);
  }
});

onUnmounted(() => {
  window.removeEventListener('gameFiltersChanged', handleFilterChange);
  
  if (chartInstance) {
    chartInstance.destroy();
  }
});

// Expose refresh method
defineExpose({
  refreshChart: async () => {
    console.log('External refresh called');
    try {
      await fetchAllData();
      drawChart();
    } catch (error) {
      console.error('Error during external refresh:', error);
    }
  },
});
</script>

<template>
  <div class="p-4 min-h-[300px] bg-gray-800 rounded shadow flex flex-col">
    <h3 class="font-semibold text-lg mb-2">Score Trends</h3>
    <div class="flex-1 flex items-center justify-center">
      <div v-if="isLoading" class="text-center text-gray-400">
        Loading chart data...
      </div>
      <div v-else-if="calculatePlayerAverages().length === 0" class="text-center text-gray-400">
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