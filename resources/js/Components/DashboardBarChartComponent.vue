<script setup>
import { ref, defineProps, onMounted, watch } from 'vue';
import VueApexCharts from "vue3-apexcharts";
import axios from 'axios';

const props = defineProps({
  gameTypeId: { // Prop for filtering by game type
    type: [String, Number],
    default: null
  },
  startDate: { // Prop for filtering by start date
    type: [Date, null],
    default: null
  },
  endDate: { // Prop for filtering by end date
    type: [Date, null],
    default: null
  },
  userId: { // Prop for filtering by user ID
    type: [String, Number],
    default: null
  }
});

// Reactive state for chart series data
const chartSeries = ref([]);

/**
 * Fetches data for the bar chart from the backend API.
 * This data represents the average score per game type, filtered by the provided props.
 */
const fetchBarChartData = async () => {
  try {
    const params = {};
    if (props.gameTypeId !== null) params.game_type_id = props.gameTypeId;
    if (props.startDate) params.start_date = props.startDate.toISOString().split('T')[0];
    if (props.endDate) params.end_date = props.endDate.toISOString().split('T')[0];
    if (props.userId !== null) params.user_id = props.userId;

    const response = await axios.get(`/dashboard/cumulative-bargraph`, { params });
    chartSeries.value = response.data;
  } catch (error) {
    console.error('Error fetching bar chart data:', error);
    chartSeries.value = []; // Clear data on error
  }
};

// Chart options for the bar chart
const chartOptions = ref({
  chart: {
    type: 'bar',
    height: 350,
    foreColor: '#ccc', // Text color for labels, etc.
    toolbar: {
      show: false // Hide toolbar (zoom, pan, etc.)
    },
  },
  plotOptions: {
    bar: {
      horizontal: false, // Vertical bars
      columnWidth: '55%', // Width of the bars
      endingShape: 'rounded', // Rounded tops for bars
      borderRadius: 6, // Border radius for bars
    },
  },
  dataLabels: {
    enabled: false // Hide data labels on bars
  },
  stroke: {
    show: true,
    width: 2,
    colors: ['transparent'] // Transparent stroke
  },
  xaxis: {
    type: 'category', // X-axis will be categories (game types)
    labels: {
      style: {
        colors: '#9CA3AF', // Color for X-axis labels
        fontSize: '12px',
      },
    },
    axisBorder: {
      show: false // Hide X-axis border
    },
    axisTicks: {
      show: false // Hide X-axis ticks
    }
  },
  yaxis: {
    title: {
      text: 'Average Score', // Y-axis title
      style: {
        color: '#9CA3AF',
        fontSize: '14px',
      }
    },
    labels: {
      formatter: function (val) {
        return val.toFixed(0) + ' pts'; // Format Y-axis labels to show 'pts'
      },
      style: {
        colors: '#9CA3AF', // Color for Y-axis labels
        fontSize: '12px',
      },
    },
  },
  fill: {
    opacity: 1 // Full opacity for bars
  },
  tooltip: {
    theme: 'dark', // Dark tooltip theme
    y: {
      formatter: function (val) {
        return val.toFixed(2) + ' points'; // Format tooltip value
      }
    }
  },
  grid: {
    show: true,
    borderColor: '#374151', // Grid line color
    strokeDashArray: 3, // Dashed grid lines
    xaxis: {
      lines: {
        show: false // Hide vertical grid lines
      }
    },
    yaxis: {
      lines: {
        show: true // Show horizontal grid lines
      }
    },
    padding: {
      top: 10,
      right: 20,
      bottom: 10,
      left: 20
    }
  },
  colors: ['#10B981'], // Bar color (a nice green)
});

// Watch for changes in filter props and refetch data
watch(
  [() => props.gameTypeId, () => props.startDate, () => props.endDate, () => props.userId],
  async () => {
    await fetchBarChartData();
  }
);

// Initial data fetch on component mount
onMounted(async () => {
  await fetchBarChartData();
});

// Expose method to refresh bar chart externally if needed
defineExpose({
  refreshBarChart: async () => {
    await fetchBarChartData();
  },
});
</script>

<template>
  <div class="p-4 h-full bg-gray-800 rounded shadow flex flex-col">
    <h3 class="font-semibold text-lg mb-2 self-start">Average Score Per Game Type</h3>
    <div class="flex-1 min-h-0 w-full chart-container">
      <!-- Only render chart if there's data -->
      <VueApexCharts
        v-if="chartSeries.length > 0 && chartSeries[0].data.length > 0"
        type="bar"
        width="100%"
        height="100%"
        :options="chartOptions"
        :series="chartSeries"
      />
      <!-- Show message when no data -->
      <div v-else class="flex items-center justify-center h-full text-gray-400">
        No average score data available for the selected filters.
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Ensure chart container respects its min-height */
.chart-container :deep(.apexcharts-canvas) {
  min-height: 229px !important;
}
</style>

