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
  gameTypeId: {
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
    default: null
  },
  showAiScores: { // New prop for AI toggle
    type: Boolean,
    default: false
  }
});

// Add emit for date filter changes
const emit = defineEmits(['update-date-filter']);

const chartContainer = ref(null);
let chart = null;
let currentData = ref([]);
let rawData = ref([]); // Store the raw data for grouping detection

// Player colors for series lines
const playerColors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#D946EF', '#6B7280', '#F43F5E', '#A855F7'];

// Local variable to hold the current scale mode for tooltip closure use
let isExpScale = false;

// Function to detect if multiple points are grouped together
const detectGroupedPoints = (seriesData, pointTimestamp, tolerance = 300000) => { // 5 minutes tolerance
  const allPoints = [];

  seriesData.forEach((series, seriesIndex) => {
    series.data.forEach((point, pointIndex) => {
      if (Math.abs(point.x - pointTimestamp) <= tolerance) {
        allPoints.push({
          seriesIndex,
          pointIndex,
          playerName: series.name,
          ...point
        });
      }
    });
  });

  return allPoints;
};

const processDataWithAiScores = (originalSeriesData) => {
  return originalSeriesData.map(series => {
    const updatedData = series.data.map(point => {
      const hasAiScore = props.showAiScores && point.meta?.ai_score !== undefined && point.meta?.ai_score !== null;

      return {
        ...point,
        markerSize: hasAiScore ? 8 : 5 // Larger marker if it has AI score
      };
    });

    return {
      ...series,
      data: updatedData
    };
  });
};



const getChartOptions = (seriesData, isExponentialScale) => {
  // Update local flag for tooltip closure
  isExpScale = isExponentialScale;
  
  // Process data to include AI scores line if enabled
  const processedSeriesData = processDataWithAiScores(seriesData);
  
  // Store raw data for grouping detection
  rawData.value = processedSeriesData;

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

  // Create colors array that includes AI series color
  const seriesColors = [...playerColors];
  if (processedSeriesData.length > seriesData.length) {
    seriesColors.push('#22c55e'); // Green for AI series
  }

  return {
    series: processedSeriesData,
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
    colors: seriesColors,
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
      custom: function({ series, seriesIndex, dataPointIndex, w}) {
        const point = w.config.series[seriesIndex].data[dataPointIndex];
        const playerName = w.globals.seriesNames[seriesIndex];
        const value = point.y;
        const timestamp = point.x;
        const date = new Date(timestamp).toLocaleString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });



        // Original tooltip logic for regular points
        const gameName = point.meta?.game_name || 'Unknown Game';
        
        // Detect grouped points
        const groupedPoints = detectGroupedPoints(rawData.value, timestamp);
        const hasGroupedPoints = groupedPoints.length > 1;

        let rateChange = '';
        if (isExpScale && dataPointIndex > 0) {
          const prevValue = w.config.series[seriesIndex].data[dataPointIndex - 1].y;
          const change = prevValue === 0 ? 0 : ((value - prevValue) / prevValue) * 100;
          const triangle = change >= 0 ? '▲' : '▼';
          const colorClass = change >= 0 ? 'text-blue-400' : 'text-red-400';

          rateChange = `
            <div class="${colorClass} text-xs italic flex items-center gap-1">
              <span>${triangle}</span>
              <span>${Math.abs(change).toFixed(2)}%</span>
            </div>`;
        }

        const isDateFilterActive = props.startDate !== null || props.endDate !== null;

        // Add grouped points notification
        let groupedPointsInfo = '';
        if (hasGroupedPoints && !isDateFilterActive) {
          const pointDate = new Date(timestamp);
          const dateStr = pointDate.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
          });

          groupedPointsInfo = `
            <div class="mt-2 pt-2 border-t border-gray-600">
              <div class="text-yellow-400 text-xs">
                ⚠️ ${groupedPoints.length} scores recorded around this time
              </div>
              <div class="mt-1">
                <a class="text-blue-400 text-xs">
                  Zoom to ${dateStr} to see all points
                </a>
              </div>
            </div>`;
        }

        // Enhanced AI Score Tooltip Logic with dark green highlighting
        let displayPlayerName = playerName;
        let aiDetails = '';
        let displayAITitle = '';
        let sessionId = point.meta.session_id;
        
        if (props.showAiScores && point.meta && point.meta.ai_score !== null) {
            displayAITitle = '<div style="background-color: #065f46; color: #10b981; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-top: 2px;">AI Model - Normal</div>';
            aiDetails = `
                <div style="background-color: #065f46; color: #10b981; padding: 4px 6px; border-radius: 4px; margin-top: 4px; font-weight: bold;">
                    AI Score: ${point.meta.ai_score}
                </div>
                `;
        }

        return `
          <div class="bg-gray-800 p-3 rounded-lg shadow-lg border border-gray-600">
            ${displayAITitle}
            ${aiDetails}
            <div class="text-white font-semibold">${displayPlayerName}</div>
            <div class="text-white text-sm">${gameName}</div>
            <div class="text-white text-sm">${date}</div>
            <div class="text-white font-medium">${value} points</div>
            <div class="text-white text-xs" style="font-size: 8pt !important;">Game Session: ${sessionId}</div>
            ${rateChange}
            ${groupedPointsInfo}
          </div>
        `;
      }
    },
    markers: getMarkersConfig(processedSeriesData),
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


// Simplified approach for AI highlighting
const getMarkersConfig = (seriesData) => {
  return {
    size: 5,
    discrete: seriesData.flatMap((series, seriesIndex) =>
      series.data.map((point, pointIndex) => {
        if (props.showAiScores && point.meta?.ai_score !== undefined && point.meta?.ai_score !== null) {
          return {
            seriesIndex,
            dataPointIndex: pointIndex,
            size: 8,
            fillColor: '#22c55e',
            strokeColor: '#065f46',
            shape: 'circle'
          };
        }
        return null;
      }).filter(Boolean)
    ),
    strokeWidth: 1,
    hover: {
      size: 9
    }
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
    yaxis: options.yaxis,
    tooltip: options.tooltip, // Make sure to update the tooltip configuration
    markers: options.markers // Update markers configuration
  }, false);

  chart.updateSeries(seriesData, true);
};

const fetchCumulativeLineGraphScores = async (gameTypeId = null, startDate = null, endDate = null, userId = null) => {
  try {
    const params = {};
    if (gameTypeId !== null) params.game_type_id = gameTypeId;
    if (startDate) params.start_date = startDate.toISOString().split('T')[0];
    if (endDate) params.end_date = endDate.toISOString().split('T')[0];
    if (userId !== null) params.user_id = userId;

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
      props.gameTypeId,
      props.startDate,
      props.endDate,
      props.userId
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
  [() => props.gameTypeId, () => props.startDate, () => props.endDate, () => props.userId, () => props.showAiScores], // Add showAiScores to watch
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