<template>
  <div class="line-chart-container">
    <div ref="chartContainer" class="w-full h-80"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue';
import axios from 'axios';
import ApexCharts from 'apexcharts';

const props = defineProps({
  gameId: {
    type: [String, Number],
    default: null
  },
  startDate: {
    type: [Date, null],
    default: null
  },
  endDate: {
    type: [Date, null],
    default: null
  },
  isExponentialScale: {
    type: Boolean,
    default: false
  },
  gameQuestions: {
    type: Array,
    default: () => []
  },
  userId: {
    type: [String, Number],
    default: null // Prop to receive the selected user ID
  }
});

const chartContainer = ref(null);
let chart = null;
let currentData = ref([]);

const playerColors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#D946EF', '#6B7280', '#F43F5E', '#A855F7'];

const getChartOptions = (seriesData, isExponentialScale) => {
  const yaxisConfig = {
    labels: {
      style: {
        fontSize: '12px',
        colors: '#9CA3AF'
      }
    }
  };

  if (isExponentialScale) {
    yaxisConfig.logarithmic = true;
    yaxisConfig.min = 0.1;
    yaxisConfig.labels.formatter = (value) => (value <= 0 ? '0 pts' : `${Math.round(value)} pts`);
  } else {
    yaxisConfig.logarithmic = false;
    yaxisConfig.min = 0;
    yaxisConfig.labels.formatter = (value) => `${Math.round(value)} pts`;
  }

  return {
    series: seriesData,
    chart: {
      type: 'line',
      height: 320,
      background: 'transparent',
      toolbar: { show: false },
      zoom: { enabled: false },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800
      }
    },
    colors: playerColors,
    stroke: {
      curve: 'smooth',
      width: 3,
      lineCap: 'round'
    },
    grid: {
      show: true,
      borderColor: '#374151',
      strokeDashArray: 3,
      xaxis: { lines: { show: false } },
      yaxis: { lines: { show: true } },
      padding: { top: 10, right: 20, bottom: 10, left: 20 }
    },
    xaxis: {
      type: 'datetime',
      labels: {
        datetimeFormatter: {
          year: 'yyyy',
          month: 'MMM \'yy',
          day: 'dd MMM',
          hour: 'HH:mm'
        },
        style: {
          fontSize: '12px',
          colors: '#9CA3AF'
        }
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
      tooltip: { enabled: false }
    },
    yaxis: yaxisConfig,
    tooltip: {
      theme: 'dark',
      style: { fontSize: '12px' },
      x: { show: false },
      custom: function({ series, seriesIndex, dataPointIndex, w }) {
        const value = series[seriesIndex][dataPointIndex];
        const playerName = w.globals.seriesNames[seriesIndex];
        const timestamp = w.globals.seriesX[seriesIndex][dataPointIndex];
        const date = new Date(timestamp).toLocaleString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });

        return `
          <div class="bg-gray-800 p-3 rounded-lg shadow-lg border border-gray-600">
            <div class="text-white font-semibold">${playerName}</div>
            <div class="text-white text-sm">${date}</div>
            <div class="text-white font-medium">${value} points</div>
          </div>
        `;
      }
    },
    markers: {
      size: 4,
      strokeColors: playerColors,
      strokeWidth: 1,
      hover: {
        size: 6
      }
    },
    legend: {
      show: true,
      position: 'bottom',
      horizontalAlign: 'center',
      fontSize: '12px',
      fontFamily: 'inherit',
      fontWeight: 400,
      labels: {
        colors: '#9CA3AF',
        useSeriesColors: true
      },
      markers: {
        width: 8,
        height: 8,
        radius: 4
      },
      itemMargin: {
        horizontal: 10,
        vertical: 5
      }
    },
    fill: {
      type: 'solid',
      opacity: 0.4
    },
    responsive: [{
      breakpoint: 768,
      options: {
        chart: {
          height: 280
        },
        xaxis: {
          labels: {
            show: true,
            maxHeight: 40,
            style: {
              fontSize: '10px'
            }
          }
        },
        legend: {
          fontSize: '10px'
        }
      }
    }]
  };
};

const initializeChart = (seriesData, isExponentialScale) => {
  const options = getChartOptions(seriesData, isExponentialScale);
  chart = new ApexCharts(chartContainer.value, options);
  chart.render();
};

const updateChart = (seriesData, isExponentialScale) => {
  const options = getChartOptions(seriesData, isExponentialScale);

  chart.updateOptions({
    yaxis: options.yaxis
  }, false); // No full animation

  chart.updateSeries(seriesData, true); // Animate series only
};

const fetchCumulativeLineGraphScores = async (gameId = null, startDate = null, endDate = null, userId = null) => {
  try {
    const params = {};
    if (gameId !== null) params.game_id = gameId;
    if (startDate) params.start_date = startDate.toISOString().split('T')[0];
    if (endDate) params.end_date = endDate.toISOString().split('T')[0];
    if (userId !== null) params.user_id = userId; // Pass user ID to the backend

    const response = await axios.get(`/dashboard/cumulative-linegraph`, { params });
    return response.data;
  } catch (error) {
    console.error('Failed to fetch game scores:', error);
    return [];
  }
};

const initOrUpdateChart = async () => {
  try {
    const seriesData = await fetchCumulativeLineGraphScores(
      props.gameId,
      props.startDate,
      props.endDate,
      props.userId // Pass the userId prop
    );
    currentData.value = seriesData;

    if (!chart) {
      initializeChart(seriesData, props.isExponentialScale);
    } else {
      updateChart(seriesData, props.isExponentialScale);
    }
  } catch (error) {
    console.error('Error initializing or updating chart:', error);
  }
};

const updateScale = () => {
  if (chart && currentData.value.length > 0) {
    updateChart(currentData.value, props.isExponentialScale);
  }
};

onMounted(async () => {
  await nextTick();
  initOrUpdateChart();
});

onUnmounted(() => {
  if (chart) {
    chart.destroy();
  }
});

watch(
  [() => props.gameId, () => props.startDate, () => props.endDate, () => props.userId], // Watch userId prop
  () => {
    initOrUpdateChart();
  },
  { immediate: false }
);

watch(
  () => props.isExponentialScale,
  () => {
    updateScale();
  },
  { immediate: false }
);
</script>

<style scoped>
.line-chart-container {
  @apply w-full;
}

::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}

::-webkit-scrollbar-track {
  @apply bg-gray-700;
}

::-webkit-scrollbar-thumb {
  @apply bg-gray-500 rounded-full;
}

::-webkit-scrollbar-thumb:hover {
  @apply bg-gray-400;
}
</style>