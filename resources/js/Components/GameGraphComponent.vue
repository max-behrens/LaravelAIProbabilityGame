<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const chartCanvas = ref(null);
const playersData = ref([]);
const props = defineProps({ gameId: Number }); // 👈 make sure you receive gameId!

onMounted(async () => {
    const response = await axios.get(`/api/player-averages?gameId=${props.gameId}`);
    playersData.value = response.data;

    const ctx = chartCanvas.value.getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: playersData.value.map(player => player.name),
            datasets: [{
                label: 'Average Score',
                data: playersData.value.map(player => player.average_score),
                backgroundColor: 'rgba(75,192,192,0.5)',
                borderColor: 'rgba(75,192,192,1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
});
</script>

<template>
    <div class="flex-1 h-80 bg-gray-800 p-4 rounded shadow">
        <h3 class="font-semibold text-lg mb-2 text-white">Score Trends</h3>
        <canvas ref="chartCanvas" class="w-full h-full"></canvas>
    </div>
</template>
