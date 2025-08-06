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

// Draw or update the chart
const drawChart = () => {
  if (!chartCanvas.value) return;
  const ctx = chartCanvas.value.getContext('2d');
  
  if (chartInstance) {
    chartInstance.destroy();
  }

  if (playersData.value.length === 0) {
    return; // Don't create chart if no data
  }

  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: playersData.value.map(player => player.name),
      datasets: [
        {
          label: 'Average Score',
          data: playersData.value.map(player => player.average_score),
          backgroundColor: playersData.value.map(() => 'rgba(54, 162, 235, 0.5)'),
          borderColor: playersData.value.map(() => 'rgba(54, 162, 235, 1)'),
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: {
          bottom: 3
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          min: 0,
          grace: '10%',
          ticks: { stepSize: 1, color: '#758096', padding: 5 },
          grid: { color: 'rgba(54, 162, 235, 0.15)' }
        },
        x: {
          ticks: { color: '#758096', padding: 10 },
          grid: { color: 'rgba(54, 162, 235, 0.15)' }
        }
      },
      plugins: {
        legend: {
          labels: { color: '#758096' }
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              const player = playersData.value[context.dataIndex];
              const avg = player.average_score !== undefined && player.average_score !== null
                ? Number(player.average_score).toFixed(2)
                : 'N/A';
              const total = player.total_score ?? 'N/A';
              return [
                `Average Score: ${avg}`,
                '',
                `Max Game Score: ${totalGameScore.value ?? 'N/A'}`
              ];
            }
          }
        }
      }
    },
  });
};


// Update handleFilterChange function
const handleFilterChange = (event) => {
  const { dateRange: newDateRange, userIds: newUserIds, andUsers: newAndUsers, excludeAI: newExcludeAI } = event.detail;
  
  dateRange.value = newDateRange;
  userIds.value = newUserIds;
  andUsers.value = newAndUsers;
  excludeAI.value = newExcludeAI !== undefined ? newExcludeAI : true;
  
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