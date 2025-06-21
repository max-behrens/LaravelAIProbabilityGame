<script setup>
import { ref, onMounted, watch } from "vue";
import { Chart, registerables } from "chart.js";

Chart.register(...registerables);

const props = defineProps({
    calculationResults: Object
});

const chartCanvas = ref(null);
let chartInstance = null;

// Function to render the chart
const renderChart = () => {
    if (!props.calculationResults) return;

    const ctx = chartCanvas.value.getContext("2d");

    if (chartInstance) {
        chartInstance.destroy(); // Destroy previous chart instance to avoid overlap
    }

    chartInstance = new Chart(ctx, {
        type: "line",
        data: {
            labels: ["Day 1", "Day 2", "Day 3", "Day 4", "Day 5"],
            datasets: [
                {
                    label: "Temperature Change (°C)",
                    data: props.calculationResults.temperatureChanges,
                    borderColor: "red",
                    backgroundColor: "rgba(255, 99, 132, 0.2)"
                },
                {
                    label: "Humidity Change (%)",
                    data: props.calculationResults.humidityChanges,
                    borderColor: "blue",
                    backgroundColor: "rgba(54, 162, 235, 0.2)"
                },
                {
                    label: "Pressure Change (hPa)",
                    data: props.calculationResults.pressureChanges,
                    borderColor: "green",
                    backgroundColor: "rgba(75, 192, 192, 0.2)"
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Forecast Days'
                    },
                    ticks: {
                        color: '#D1D5DB' // Light gray for x-axis labels in dark mode
                    },
                    grid: {
                        color: '#374151' // Dark grid lines
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Rate of Change per Day',
                        color: '#D1D5DB' // Light gray for y-axis title
                    },
                    ticks: {
                        color: '#D1D5DB', // Light gray for y-axis ticks
                        callback: function(value) {
                            return value + ' units';
                        }
                    },
                    grid: {
                        color: '#374151' // Dark grid lines
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#D1D5DB' // Legend text in light gray
                    }
                }
            }
        }
    });
};

// Watch for updates in calculationResults and re-render the chart
watch(() => props.calculationResults, renderChart, { deep: true });

onMounted(renderChart);
</script>

<template>
    <div class="w-full h-96 bg-gray-900 p-6 shadow-lg rounded-lg border border-gray-700">
        <canvas ref="chartCanvas"></canvas>
    </div>
</template>
