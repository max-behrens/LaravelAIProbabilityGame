<template>
  <div class="line-chart-container">
    <div class="mb-4">
      <h3 class="text-xl font-semibold text-white mb-2">Performance Over Time</h3>
      <p class="text-gray-400 text-sm">Track your game performance and progress</p>
    </div>
    <div ref="chartContainer" class="w-full h-80"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue';
import axios from 'axios';


// Props
const props = defineProps({
  gameId: {
    type: [String, Number],
    default: null
  },
  gameQuestions: {
    type: Array,
    default: () => []
  }
});

// Refs
const chartContainer = ref(null);
let chart = null;

// Chart configuration
const createChart = (seriesData) => {
  const options = {
    series: seriesData, // Use the fetched series data
    chart: {
      type: 'line',
      height: 320,
      background: 'transparent',
      toolbar: {
        show: false
      },
      zoom: {
        enabled: false
      },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800
      }
    },
    stroke: {
      curve: 'smooth',
      width: 3,
      lineCap: 'round'
    },
    grid: {
      show: true,
      borderColor: '#374151',
      strokeDashArray: 3,
      xaxis: {
        lines: {
          show: false
        }
      },
      yaxis: {
        lines: {
          show: true
        }
      },
      padding: {
        top: 10,
        right: 20,
        bottom: 10,
        left: 20
      }
    },
    xaxis: {
      type: 'datetime', // Changed to datetime for timestamps
      labels: {
        datetimeFormatter: {
          year: 'yyyy',
          month: 'MMM \'yy',
          day: 'dd MMM',
          hour: 'HH:mm'
        },
        style: {
          colors: '#9CA3AF',
          fontSize: '12px'
        }
      },
      axisBorder: {
        show: false
      },
      axisTicks: {
        show: false
      }
    },
    yaxis: {
      min: 0,
      labels: {
        style: {
          colors: '#9CA3AF',
          fontSize: '12px'
        },
        formatter: (value) => `${value} pts` // Changed formatter to points
      }
    },
    tooltip: {
      theme: 'dark',
      style: {
        fontSize: '12px'
      },
      x: {
        format: 'dd MMM yyyy HH:mm' // Format for datetime tooltip
      },
      custom: function({ series, seriesIndex, dataPointIndex, w }) {
        const value = series[seriesIndex][dataPointIndex];
        const playerName = w.globals.seriesNames[seriesIndex];
        const timestamp = w.globals.seriesX[seriesIndex][dataPointIndex];
        const date = new Date(timestamp).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });

        return `
          <div class="bg-gray-800 p-3 rounded-lg shadow-lg border border-gray-600">
            <div class="text-white font-semibold">${playerName}</div>
            <div class="text-gray-400 text-sm">${date}</div>
            <div class="text-blue-400 font-medium">${value.toFixed(0)} points total</div>
          </div>
        `;
      }
    },
    markers: {
      size: 6,
      colors: ['#3B82F6'], // These will be overridden by series colors if provided
      strokeColors: '#1E40AF',
      strokeWidth: 2,
      hover: {
        size: 8
      }
    },
    fill: {
      type: 'gradient',
      gradient: {
        shade: 'dark',
        type: 'vertical',
        shadeIntensity: 0.1,
        // gradientToColors: ['#1E40AF'], // This will be handled by ApexCharts for multiple series
        inverseColors: false,
        opacityFrom: 0.8,
        opacityTo: 0.1,
        stops: [0, 100]
      }
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
        }
      }
    }]
  };

  // Initialize ApexCharts
  if (chart) {
    chart.updateOptions(options); // Update existing chart
  } else if (window.ApexCharts) {
    chart = new window.ApexCharts(chartContainer.value, options);
    chart.render();
  } else {
    console.error('ApexCharts is not loaded');
  }
};

// Load ApexCharts dynamically
const loadApexCharts = () => {
  return new Promise((resolve, reject) => {
    if (window.ApexCharts) {
      resolve();
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.min.js';
    script.onload = resolve;
    script.onerror = reject;
    document.head.appendChild(script);
  });
};

// Function to fetch cumulative scores
const fetchCumulativeScores = async () => {
  try {
    const response = await axios.get(`/dashboard/cumulative-scores`);
    return response.data; // axios automatically parses JSON
  } catch (error) {
    console.error('Failed to fetch cumulative scores:', error);
    return [];
  }
};

// Lifecycle hooks
onMounted(async () => {
  try {
    await loadApexCharts();
    await nextTick();
    const seriesData = await fetchCumulativeScores(); // Remove gameId parameter
    createChart(seriesData);
  } catch (error) {
    console.error('Failed to load ApexCharts or fetch data:', error);
  }
});

// Method to update chart data (can be called from parent) - now not directly used by parent
// defineExpose({
//   updateChartData
// });
</script>

<style scoped>
.line-chart-container {
  @apply w-full;
}

/* Custom scrollbar for responsive design */
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