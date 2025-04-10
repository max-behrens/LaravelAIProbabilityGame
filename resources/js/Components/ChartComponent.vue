<script setup>
import { ref, onMounted } from "vue";
import { Chart, registerables } from "chart.js";
import axios from "axios";

Chart.register(...registerables);

const chartCanvas = ref(null);

const fetchChartData = async () => {
    try {
        const { data } = await axios.get('/api/chart-data');
        return data;
    } catch (error) {
        console.error("Error fetching chart data:", error);
        return { labels: [], values: [] };
    }
};

onMounted(async () => {
    const ctx = chartCanvas.value.getContext('2d');
    const chartData = await fetchChartData();

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Sample Data',
                data: chartData.values,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>

<template>
    <div class="w-full h-96 p-4 shadow-md rounded-lg">
        <h3 class="text-lg font-bold mb-2">Data Visualization</h3>
        <canvas ref="chartCanvas"></canvas>
    </div>
</template>
