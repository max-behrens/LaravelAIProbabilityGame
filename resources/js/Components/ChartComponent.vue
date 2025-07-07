

<script setup>
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Chart } from 'chart.js/auto';

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

// Fetch player averages
const fetchScoreTrendStats = async () => {
  try {
    const response = await axios.get(`/api/games/${props.gameId}/score-trends`);
    playersData.value = response.data.players;
    totalGameScore.value = response.data.totalScore;
  } catch (error) {
    console.error('Error fetching player averages:', error);
  }
};

// Draw or update the chart
const drawChart = () => {
  if (!chartCanvas.value) return;

  const ctx = chartCanvas.value.getContext('2d');

  if (chartInstance) {
    chartInstance.destroy();
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
          ticks: { color: '#758096', padding: 10, },
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
  await fetchScoreTrendStats();
  drawChart();
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
        No player data available.
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
