

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

// Fetch player averages
const fetchPlayerAverages = async () => {
  try {
const response = await axios.get(`/api/games/${props.gameId}/player-averages`);
    playersData.value = response.data;
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
      scales: {
        y: {
          beginAtZero: true,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          min: 0,
          grace: '10%',
          ticks: { stepSize: 1, color: '#e5e7eb' }, // gray-200
          grid: { color: 'rgba(255,255,255,0.1)' }
        },
        x: {
          ticks: { color: '#e5e7eb', padding: 25, }, // gray-200
          grid: { color: 'rgba(255,255,255,0.1)' }
        }
      },
      plugins: {
        legend: {
          labels: { color: '#e5e7eb' } // gray-200
        }
      }
    },
  });
};

// Watch for gameId changes
watch(
  () => props.gameId,
  async () => {
    await fetchPlayerAverages();
    drawChart();
  }
);

// Initial chart render
onMounted(async () => {
  await fetchPlayerAverages();
  drawChart();
});

// Expose method to refresh chart externally
defineExpose({
  refreshChart: async () => {
    await fetchPlayerAverages();
    drawChart();
  },
});
</script>


<template>
  <div class="h-80 p-4 bg-gray-800 rounded shadow text-gray-200 relative">
    <h3 class="font-semibold text-lg mb-2">Score Trends</h3>
    <div v-if="playersData.length === 0" class="text-center text-sm text-gray-400">
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
</template>
