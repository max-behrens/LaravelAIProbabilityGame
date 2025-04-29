<script setup>
import { ref, onMounted, defineExpose } from 'vue';
import axios from 'axios';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const chartCanvas = ref(null);
const playersData = ref([]);
const props = defineProps({ gameId: Number });

let chartInstance = null;

const fetchPlayerAverages = async () => {
  try {
    const response = await axios.get(`/api/player-averages?gameId=${props.gameId}`);
    playersData.value = response.data;

    if (chartInstance) {
      // Update existing chart
      chartInstance.data.labels = playersData.value.map(player => player.name);
      chartInstance.data.datasets[0].data = playersData.value.map(player => player.average_score);
      chartInstance.update();
    }
  } catch (err) {
    console.error('Failed to fetch player averages:', err);
  }
};

onMounted(async () => {
  await fetchPlayerAverages();

  const ctx = chartCanvas.value.getContext('2d');
  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: playersData.value.map(player => player.name),
      datasets: [{
        label: 'Average Score',
        data: playersData.value.map(player => player.average_score),
        backgroundColor: 'rgba(75, 192, 192, 0.5)',
        borderColor: 'rgba(75, 192, 192, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: { stepSize: 1 }
        }
      }
    }
  });
});

// Expose method to parent so it can refresh the chart on demand
defineExpose({ fetchPlayerAverages });
</script>

<template>
  <div class="flex-1 h-80 bg-gray-800 p-4 rounded shadow">
    <h3 class="font-semibold text-lg mb-2 text-white">Score Trends</h3>
    <canvas ref="chartCanvas" class="w-full h-full"></canvas>
  </div>
</template>
