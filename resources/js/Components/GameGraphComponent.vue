<script setup>
import { ref, onMounted } from 'vue';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const chartCanvas = ref(null);

onMounted(() => {
    const ctx = chartCanvas.value.getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Round 1', 'Round 2', 'Round 3', 'Round 4'],
            datasets: [
                {
                    label: 'Alice',
                    data: [65, 59, 80, 81],
                    borderColor: 'rgba(75,192,192,1)',
                    backgroundColor: 'rgba(75,192,192,0.2)',
                    tension: 0.4
                },
                {
                    label: 'Bob',
                    data: [28, 48, 40, 19],
                    borderColor: 'rgba(153,102,255,1)',
                    backgroundColor: 'rgba(153,102,255,0.2)',
                    tension: 0.4
                },
                {
                    label: 'Charlie',
                    data: [40, 50, 60, 70],
                    borderColor: 'rgba(255,159,64,1)',
                    backgroundColor: 'rgba(255,159,64,0.2)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#FFFFFF' // White text for legend
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#FFFFFF' // White color for x-axis ticks
                    }
                },
                y: {
                    ticks: {
                        color: '#FFFFFF' // White color for y-axis ticks
                    }
                }
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
