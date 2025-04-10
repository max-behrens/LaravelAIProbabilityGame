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
                    // Add unit to x-axis label (optional)
                    title: {
                        display: true,
                        text: 'Forecast Days' // This is the label for the x-axis
                    }
                },
                y: {
                    // Add unit to y-axis label
                    title: {
                        display: true,
                        text: 'Rate of Change per Day' // This is the generic label for the y-axis, but you can customize this
                    },
                    ticks: {
                        callback: function(value) {
                            return value + ' units'; // Replace with the appropriate unit for your data (e.g., °C, %, hPa)
                        }
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
    <div class="w-full h-96 bg-gray-200 p-4 shadow-md rounded-lg">
        <canvas ref="chartCanvas"></canvas>
    </div>
</template>
