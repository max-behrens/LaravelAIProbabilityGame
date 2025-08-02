<script setup>
import { ref, defineProps, onMounted, watch, computed, nextTick } from 'vue';
import VueApexCharts from "vue3-apexcharts";
import axios from 'axios';
import dayjs from 'dayjs';

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
  userId: {
    type: [String, Number],
    default: null
  },
  showAiScores: {
    type: Boolean,
    default: false
  }
});

// Pagination & heatmap data state
const heatmapData = ref([]);
const heatmapLoading = ref(false);

const selectedSession = ref(null);
const selectedSessionDetails = ref(null);
const isLoadingDetails = ref(false);
const chartKey = ref(0);
const selectedPlayerName = ref(null);

const selectedPlayerIndex = ref(null);
const hoveredPlayerIndex = ref(null);

// NEW: Scrollable window state
const windowSize = 30; // Show 30 days at a time
const currentWindowStart = ref(0); // Index of the first day in the current window
const allDates = ref([]); // All possible dates in the range

/**
 * Fetch ALL heatmap data from API with filters
 */
const fetchHeatmapData = async () => {
  heatmapLoading.value = true;
  try {
    const params = {};
    
    // Only add game_type_id if a specific game is selected
    if (props.gameTypeId !== null && props.gameTypeId !== undefined) {
      params.game_type_id = props.gameTypeId;
    }
    
    if (props.startDate) params.start_date = dayjs(props.startDate).format('YYYY-MM-DD');
    if (props.endDate) params.end_date = dayjs(props.endDate).format('YYYY-MM-DD');
    if (props.userId !== null && props.userId !== undefined) {
      params.user_id = props.userId;
    }
    
    // Fetch a large number to get all data
    params.per_page = 1000;

    console.log('Fetching heatmap with params:', params);

    const response = await axios.get('/dashboard/cumulative-heatmap', { params });
    heatmapData.value = response.data.data || [];

    console.log('Received heatmap data:', heatmapData.value.length, 'sessions');

    // Generate all dates and reset window
    allDates.value = generateAllDates();
    currentWindowStart.value = Math.max(0, allDates.value.length - windowSize); // Start at the end (most recent)

    await nextTick();

    // Auto-select first session after loading
    if (heatmapData.value.length > 0) {
      const firstSession = heatmapData.value[0];
      selectedSession.value = {
        sessionId: firstSession.session_id,
        players: firstSession.players,
        gameName: firstSession.game_name,
        score: firstSession.combined_score
      };
      await fetchSessionDetails(firstSession.session_id);
    } else {
      selectedSession.value = null;
      selectedSessionDetails.value = null;
    }

    // Force chart re-render
    chartKey.value++;
  } catch (error) {
    console.error('Failed to load heatmap data', error);
    heatmapData.value = [];
    selectedSession.value = null;
    selectedSessionDetails.value = null;
  } finally {
    heatmapLoading.value = false;
  }
};

/**
 * Fetch session details by sessionId - with better error handling
 */
const fetchSessionDetails = async (sessionId) => {
  if (!sessionId) return;

  isLoadingDetails.value = true;
  try {
    const response = await axios.get(`/dashboard/session-details/${sessionId}`);
    selectedSessionDetails.value = response.data;
    
    // Debug log to see what data structure we're getting
    console.log('Session details for', sessionId, ':', response.data);
    
  } catch (error) {
    console.error('Error fetching session details:', error);
    selectedSessionDetails.value = null;
  } finally {
    isLoadingDetails.value = false;
  }
};

/**
 * Generate ALL possible dates in the range (not windowed)
 */
const generateAllDates = () => {
  // If both start and end dates are explicitly set, use them
  if (props.startDate && props.endDate) {
    const startDate = dayjs(props.startDate);
    const endDate = dayjs(props.endDate);
    const dates = [];
    let currentDate = startDate;
    while (currentDate.isBefore(endDate) || currentDate.isSame(endDate, 'day')) {
      dates.push(currentDate.format('YYYY-MM-DD'));
      currentDate = currentDate.add(1, 'day');
    }
    return dates;
  }

  // If we have session data, use it to determine the full range (even when no date filters)
  if (heatmapData.value.length > 0) {
    const sessionDates = heatmapData.value
      .filter(item => item.created_at)
      .map(item => dayjs(item.created_at).format('YYYY-MM-DD'));

    if (sessionDates.length > 0) {
      const earliestSession = dayjs(Math.min(...sessionDates.map(d => new Date(d).getTime())));
      const latestSession = dayjs(Math.max(...sessionDates.map(d => new Date(d).getTime())));

      // Use explicit dates if provided, otherwise use session data range with some padding
      const startDate = props.startDate ? dayjs(props.startDate) : earliestSession.subtract(3, 'day');
      const endDate = props.endDate ? dayjs(props.endDate) : latestSession.add(3, 'day');

      const dates = [];
      let currentDate = startDate;
      while (currentDate.isBefore(endDate) || currentDate.isSame(endDate, 'day')) {
        dates.push(currentDate.format('YYYY-MM-DD'));
        currentDate = currentDate.add(1, 'day');
      }
      return dates;
    }
  }

  // Fallback: last 30 days (only when no session data exists)
  const endDate = dayjs();
  const startDate = endDate.subtract(29, 'day');
  const dates = [];
  let currentDate = startDate;
  while (currentDate.isBefore(endDate) || currentDate.isSame(endDate, 'day')) {
    dates.push(currentDate.format('YYYY-MM-DD'));
    currentDate = currentDate.add(1, 'day');
  }
  return dates;
};

/**
 * Get the current window of dates to display
 */
const currentWindowDates = computed(() => {
  const start = currentWindowStart.value;
  const end = Math.min(start + windowSize, allDates.value.length);
  return allDates.value.slice(start, end);
});

// Base color ranges for non-AI scores
const baseHeatmapColorRanges = [
  { from: 0, to: 0, color: '#1f293700', name: 'No Activity' }, // Keep this for the background/no activity
  // Progressively darker blues
  { from: 1, to: 25, color: '#bcddf7', name: '1-25 pts' },   // Lightest blue
  { from: 26, to: 50, color: '#64B5F6', name: '26-50 pts' },
  { from: 51, to: 75, color: '#42A5F5', name: '51-75 pts' },
  { from: 76, to: 100, color: '#2196F3', name: '76-100 pts' },
  { from: 101, to: 1000, color: '#1976D2', name: '100+ pts' } // Darkest blue
];

// AI specific color ranges
const aiHeatmapColorRanges = [
  { from: 0, to: 0, color: '#1f2937', name: 'No Activity' },
  { from: 1, to: 25, color: '#14532d', name: 'AI 1-25 pts' },   // Green-900
  { from: 26, to: 50, color: '#166534', name: 'AI 26-50 pts' }, // Green-800
  { from: 51, to: 75, color: '#15803d', name: 'AI 51-75 pts' }, // Green-700
  { from: 76, to: 100, color: '#16a34a', name: 'AI 76-100 pts' },// Green-600
  { from: 101, to: 1000, color: '#22c55e', name: 'AI 100+ pts' }// Green-500
];


// Computed property for heatmap color ranges
const activeHeatmapColorRanges = computed(() => {
  if (props.showAiScores) {
    return aiHeatmapColorRanges;
  }
  return baseHeatmapColorRanges;
});

/**
 * Transform heatmapData into series with sessions stacked by date (windowed)
 */
// NEW: Updated heatmapSeries computed property with mixed color support
const heatmapSeries = computed(() => {
  const dates = currentWindowDates.value;
  
  const sessionsByDate = {};
  heatmapData.value.forEach(session => {
    if (!session.created_at) return;
    const date = dayjs(session.created_at).format('YYYY-MM-DD');
    if (!sessionsByDate[date]) sessionsByDate[date] = [];
    sessionsByDate[date].push(session);
  });
  Object.keys(sessionsByDate).forEach(date => {
    sessionsByDate[date].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
  });
  
  const maxSessionsPerDay = Math.max(1, ...Object.values(sessionsByDate).map(sessions => sessions.length));
  
  const series = [];
  for (let sessionIndex = 0; sessionIndex < maxSessionsPerDay; sessionIndex++) {
    const rowData = dates.map(date => {
      const sessionsForDate = sessionsByDate[date] || [];
      const session = sessionsForDate[sessionIndex] || null;
      const hasAiScore = session?.ai_score !== null && session?.ai_score !== undefined;

      let scoreValue = null;
      let useAiColors = false; // New flag to determine which color scheme to use
      let percentageScore = 0;
      let aiPercentageScore = 0;
      let yValue;
            
      if (session) {
        if (props.showAiScores && hasAiScore) {
          // AI Scores are ON and the session has an AI score - show AI score with AI colors
          scoreValue = session.ai_score;
          useAiColors = true;
        } else if (session.combined_score !== null) {
          // Show human score with human colors (whether AI mode is on or off)
          scoreValue = session.combined_score;
          useAiColors = false;
        }
      }

      // Calculate Percentage Score
      if (scoreValue !== null) {
        percentageScore = ((session.combined_score / (session.total_game_score * session.player_count)) * 100).toFixed(2);;
        aiPercentageScore = (session.ai_score / session.total_game_score) * 100;
      }

      // The y value needs to be a small negative number to distinguish 0 from null
      if (scoreValue === 0) {
        yValue = -100; // Use a distinct negative value for sessions with a score of 0
      } else {
        if (props.showAiScores && hasAiScore) {
          yValue = aiPercentageScore;
        } else {
          yValue = percentageScore;
        }
      }
      
      // Offset AI scores by 1000 to use different color ranges
      if (useAiColors && yValue > 0) {
        yValue = yValue + 1000;
      }

      return {
        x: date,
        y: yValue,
        sessionId: session?.session_id ?? null,
        players: session?.players ?? [],
        gameName: session?.game_name ?? null,
        session: session,
        createdAt: session?.created_at ?? null,
        hasAiScore: hasAiScore,
        aiPercentageScore: aiPercentageScore,
        percentageScore: percentageScore,
        useAiColors: useAiColors,
        originalScore: scoreValue, // Keep original score for display
        customdata: {
          sessionId: session?.session_id,
          useAiColors: useAiColors
        }
      };
    });

    series.push({
      name: `Session ${sessionIndex + 1}`,
      data: rowData
    });
  }
  return series;
});

/**
 * X-axis categories (current window dates)
 */
const heatmapXAxisCategories = computed(() => currentWindowDates.value);

// NEW: Navigation functions
const canNavigateLeft = computed(() => currentWindowStart.value > 0);
const canNavigateRight = computed(() => currentWindowStart.value + windowSize < allDates.value.length);

const navigateLeft = () => {
  if (canNavigateLeft.value) {
    currentWindowStart.value = Math.max(0, currentWindowStart.value - windowSize);
    chartKey.value++; // Force chart re-render
  }
};

const navigateRight = () => {
  if (canNavigateRight.value) {
    currentWindowStart.value = Math.min(allDates.value.length - windowSize, currentWindowStart.value + windowSize);
    chartKey.value++; // Force chart re-render
  }
};

const jumpToStart = () => {
  currentWindowStart.value = 0;
  chartKey.value++;
};

const jumpToEnd = () => {
  currentWindowStart.value = Math.max(0, allDates.value.length - windowSize);
  chartKey.value++;
};

// NEW: Computed properties for navigation info
const currentWindowInfo = computed(() => {
  if (allDates.value.length === 0) return '';
  
  const start = currentWindowStart.value;
  const end = Math.min(start + windowSize, allDates.value.length);
  const startDate = allDates.value[start];
  const endDate = allDates.value[end - 1];
  
  return `${startDate} to ${endDate} (${end - start} days)`;
});

const totalDaysInfo = computed(() => {
  return `${allDates.value.length} total days`;
});

const selectedHeatmapPoint = ref({ seriesIndex: null, dataPointIndex: null });

// Heatmap chart options with mixed color support
const heatmapOptions = ref({
  chart: {
    type: 'heatmap',
    height: 400,
    foreColor: '#ccc',
    toolbar: { show: false },
    animations: { enabled: false },
    events: {
      dataPointSelection: async (event, chartContext, config) => {
        const selected = heatmapSeries.value[config.seriesIndex].data[config.dataPointIndex];
        if (selected?.sessionId) {
          selectedSession.value = {
            sessionId: selected.sessionId,
            players: selected.players,
            score: selected.originalScore, // Use original score for display
            gameName: selected.gameName
          };
          selectedHeatmapPoint.value = {
            seriesIndex: config.seriesIndex,
            dataPointIndex: config.dataPointIndex
          };
          await fetchSessionDetails(selected.sessionId);
        }
      }
    }
  },
  plotOptions: {
    heatmap: {
      enableShades: false,
      distributed: false,
      colorScale: {
        ranges: [

        { from: -100, to: -100, color: '#c96565', name: '0 pts' },
          // No activity
          { from: 0, to: 0, color: '#1f2937', name: 'No Activity' },
          
          // Human scores (blue) - ranges 1-1000
          { from: 1, to: 25, color: '#96b9d4', name: '1-25' },
          { from: 26, to: 50, color: '#64B5F6', name: '26-50' },
          { from: 51, to: 75, color: '#42A5F5', name: '51-75' },
          { from: 76, to: 100, color: '#1776c2', name: '76-100' },
          
          // AI scores (green) - ranges 1001-2000
          { from: 1001, to: 1025, color: '#97c2a7', name: 'AI 1-25' },
          { from: 1026, to: 1050, color: '#86dba6', name: 'AI 26-50' },
          { from: 1051, to: 1075, color: '#4dc97b', name: 'AI 51-75' },
          { from: 1076, to: 1100, color: '#16a34a', name: 'AI 76-100' },
        ]
      },
      dataLabels: {
        enabled: false,
        formatter: function (val, opts) {
          // Attach sessionId to DOM rect via data attribute
          const sessionId = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex]?.customdata?.sessionId;
          const el = opts.el;
          if (el && sessionId) {
            el.setAttribute('data-sessionid', sessionId);
          }
          return ''; // no label
        }
      }
    }
  },
  dataLabels: { enabled: false },
  grid: { 
    padding: { right: 40, left: 40, top: 10, bottom: 10 },
    borderColor: '#374151'
  },
  yaxis: {
    show: false
  },
  xaxis: {
    type: 'category',
    categories: heatmapXAxisCategories.value,
    labels: {
      style: { colors: '#9CA3AF', fontSize: '11px' },
      rotate: -45
    }
  },
  fill: {
    opacity: 1
  },
  states: {
    active: {
      filter: {
        type: 'darken',
        value: 1,
      }
    },
    normal: {
      filter: {
        type: 'darken',
        value: 1,
      }
    },
    hover: {
      filter: {
        type: 'darken',
        value: 1,
      }
    },
  },
  tooltip: {
    custom({ series, seriesIndex, dataPointIndex, w }) {
      const cellData = w.globals.initialSeries[seriesIndex].data[dataPointIndex];
      const session = cellData.session;

      if (!session) {
        return `<div style="padding:8px; background-color:#1e1e1e; color:#cccccc;">No session data</div>`;
      }

      const time = new Date(session.created_at).toLocaleTimeString('en-US', {
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });

      let playersToShow = session.players;
      if (selectedSessionDetails.value && 
          selectedSessionDetails.value.session_id === session.truncated_session_id && 
          selectedSessionDetails.value.players) {
        playersToShow = selectedSessionDetails.value.players;
      }

      const playersHtml = playersToShow.map(player => 
        `<div style="margin-left: 10px; font-size: 11px;">
          ${player.player_name || player.name}: ${player.total_score} pts
        </div>`
      ).join('');
      
      const aiIndicator = cellData.hasAiScore 
        ? '<span style="color: #10b981; font-size: 10px;">🤖 AI Present</span><br/>'
        : '';
        
      const aiPercentageScoreDisplay = (cellData.hasAiScore && props.showAiScores)
        ? `<span style="font-size: 10px;">AI % Score:</span> <span style="color: #10b981;">${cellData.aiPercentageScore}</span><br/>`
        : '';


      return `
        <div style="padding:8px; background-color:#1e1e1e; color:#cccccc; border-radius:4px; max-width:300px;">
          <strong>Game:</strong> ${session.game_name}<br/>
          <strong>Date:</strong> ${cellData.x}<br/>
          <strong>Time:</strong> ${time}<br/>
          <strong>% Score:</strong> ${cellData.percentageScore}<br/>
          <strong>Players:</strong><br/>
          ${playersHtml}
          <div style="margin-top: 4px; font-size: 8pt; color: #888;">
            Session: ${session.truncated_session_id}
          </div>
          ${aiIndicator}
          ${aiPercentageScoreDisplay}
        </div>
      `;
    }
  },
});

// Update chart options on data change
watch([heatmapSeries, activeHeatmapColorRanges], () => {
  heatmapOptions.value = {
    ...heatmapOptions.value,
    xaxis: {
      ...heatmapOptions.value.xaxis,
      categories: heatmapXAxisCategories.value
    }
  };
  
  // Force chart re-render when data changes
  chartKey.value++;
  
}, { deep: true });

// Watch for selection changes to apply styling
watch(selectedSession, () => {
  
}, { deep: true });

// Separate watcher for showAiScores that only triggers re-render
watch(() => props.showAiScores, () => {
  // Only trigger chart re-render, don't fetch new data or reset selection
  chartKey.value++;
});

// Watch chartKey to apply styling after any re-render
watch(chartKey, () => {
  
});

// Radial chart setup (unchanged from original)
const radialChartData = computed(() => {
  const details = selectedSessionDetails.value;
  if (!details || !details.players) {
    return {
      series: [],
      labels: [],
      rawScores: []
    };
  }

  const total = details.total_score || 0;

  const playerData = details.players.map(p => {
    const raw = p.total_score || 0;
    const percent = total > 0 ? (raw / total) * 100 : 0;
    return {
      label: p.player_name || 'Player',
      raw,
      percent
    };
  });

  if (details.ai_score !== null && details.ai_score !== undefined) {
    const raw = details.ai_score;
    const percent = total > 0 ? (raw / total) * 100 : 0;
    playerData.push({ label: 'AI', raw, percent });
  }

  return {
    series: playerData.map(p => p.percent),
    labels: playerData.map(p => p.label),
    rawScores: playerData.map(p => p.raw)
  };
});

const radialColors = [
  '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
  '#EC4899', '#D946EF', '#6B7280', '#F43F5E', '#A855F7'
];

const radialOptions = ref({
  chart: {
    type: 'radialBar',
    height: 200,
    width: '100%',
    animations: { enabled: false },
    toolbar: { show: false }
  },
  plotOptions: {
    radialBar: {
      hollow: { 
        size: '50%',
        background: 'transparent'
      },
      track: { 
        background: '#1f2937',
        strokeWidth: '97%'
      },
      dataLabels: {
        show: true,
        name: {
          show: true,
          fontSize: '12px',
          fontWeight: 400,
          color: '#969696',
          offsetY: 0,
          formatter: function() {
            if (selectedPlayerIndex.value !== null || hoveredPlayerIndex.value !== null) {
              const index = hoveredPlayerIndex.value !== null ? hoveredPlayerIndex.value : selectedPlayerIndex.value;
              const playerScore = radialChartData.value.rawScores?.[index] || 0;
              const totalScore = selectedSessionDetails.value?.total_score || 0;
              return `${playerScore} / ${totalScore}`;
            }
            return '';
          }
        },
        value: {
          show: false
        },
        total: {
          show: false
        }
      }
    }
  },
  labels: [],
  tooltip: { enabled: false },
  colors: radialColors
});

watch(radialChartData, (newData) => {
  if (newData && newData.labels) {
    radialOptions.value = {
      ...radialOptions.value,
      labels: newData.labels,
      chart: {
        ...radialOptions.value.chart,
        events: {
          dataPointSelection: function (event, chartContext, config) {
            const index = config.dataPointIndex;
            selectedPlayerIndex.value = index;
            setTimeout(() => {
              radialOptions.value = {
                ...radialOptions.value,
                plotOptions: {
                  ...radialOptions.value.plotOptions,
                  radialBar: {
                    ...radialOptions.value.plotOptions.radialBar,
                    dataLabels: {
                      ...radialOptions.value.plotOptions.radialBar.dataLabels,
                      name: {
                        ...radialOptions.value.plotOptions.radialBar.dataLabels.name,
                        formatter: function() {
                          if (selectedPlayerIndex.value !== null || hoveredPlayerIndex.value !== null) {
                            const displayIndex = hoveredPlayerIndex.value !== null ? hoveredPlayerIndex.value : selectedPlayerIndex.value;
                            const playerScore = radialChartData.value.rawScores?.[displayIndex] || 0;
                            const totalScore = selectedSessionDetails.value?.total_score || 0;
                            return `${playerScore} / ${totalScore}`;
                          }
                          return '';
                        }
                      }
                    }
                  }
                }
              };
            }, 50);
          },
          dataPointMouseEnter: function(event, chartContext, config) {
            const index = config.dataPointIndex;
            hoveredPlayerIndex.value = index;
            radialOptions.value = {
              ...radialOptions.value,
              plotOptions: {
                ...radialOptions.value.plotOptions,
                radialBar: {
                  ...radialOptions.value.plotOptions.radialBar,
                  dataLabels: {
                    ...radialOptions.value.plotOptions.radialBar.dataLabels,
                    name: {
                      ...radialOptions.value.plotOptions.radialBar.dataLabels.name,
                      formatter: function() {
                        if (hoveredPlayerIndex.value !== null) {
                          const playerScore = radialChartData.value.rawScores?.[hoveredPlayerIndex.value] || 0;
                          const totalScore = selectedSessionDetails.value?.total_score || 0;
                          return `${playerScore} / ${totalScore}`;
                        }
                        return '';
                      }
                    }
                  }
                }
              }
            };
          },
          dataPointMouseLeave: function(event, chartContext, config) {
            hoveredPlayerIndex.value = null;
            radialOptions.value = {
              ...radialOptions.value,
              plotOptions: {
                ...radialOptions.value.plotOptions,
                radialBar: {
                  ...radialOptions.value.plotOptions.radialBar,
                  dataLabels: {
                    ...radialOptions.value.plotOptions.radialBar.dataLabels,
                    name: {
                      ...radialOptions.value.plotOptions.radialBar.dataLabels.name,
                      formatter: function() {
                        if (selectedPlayerIndex.value !== null) {
                          const playerScore = radialChartData.value.rawScores?.[selectedPlayerIndex.value] || 0;
                          const totalScore = selectedSessionDetails.value?.total_score || 0;
                          return `${playerScore} / ${totalScore}`;
                        }
                        return '';
                      }
                    }
                  }
                }
              }
            };
          }
        }
      }
    };
  }
}, { deep: true });

watch(selectedSessionDetails, () => {
  selectedPlayerName.value = null;
  selectedPlayerIndex.value = null;
  hoveredPlayerIndex.value = null;
}, { deep: true });

watch(() => props.showAiScores, () => {
  // Only trigger chart re-render, don't fetch new data or reset selection
  chartKey.value++;
});

// Watch filters props to refetch heatmap data
watch(
  [() => props.gameTypeId, () => props.startDate, () => props.endDate, () => props.userId],
  () => fetchHeatmapData(),
  { immediate: false }
);

// Initial load
onMounted(() => {
  fetchHeatmapData();
});
</script>

<template>
  <div class="flex h-full text-white">
    <div class="w-1/2 p-4 border-r border-gray-700">
      <div class="h-full flex flex-col">
        <div class="mb-4 text-sm text-center">
          <div class=" space-x-2">
            <button 
              @click="jumpToStart"
              :disabled="!canNavigateLeft"
              :class="[
                'px-2 py-1 rounded text-xs',
                canNavigateLeft 
                  ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                  : 'bg-gray-300 text-gray-500'
              ]"
            >
              ⟪ Start
            </button>
            <button 
              @click="navigateLeft"
              :disabled="!canNavigateLeft"
              :class="[
                'px-3 py-1 rounded text-xs',
                canNavigateLeft 
                  ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                  : 'bg-gray-300 text-gray-500'
              ]"
            >
              ← Prev
            </button>
            <button 
              @click="navigateRight"
              :disabled="!canNavigateRight"
              :class="[
                'px-3 py-1 rounded text-xs',
                canNavigateRight 
                  ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                  : 'bg-gray-300 text-gray-500'
              ]"
            >
              Next →
            </button>
            <button 
              @click="jumpToEnd"
              :disabled="!canNavigateRight"
              :class="[
                'px-2 py-1 rounded text-xs',
                canNavigateRight 
                  ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                  : 'bg-gray-300 text-gray-500'
              ]"
            >
              End ⟫
            </button>
          </div>
        </div>

        <div class="text-white !text-center">Percentage Scores</div>

        <div class="flex-1">
          <div v-if="heatmapLoading" class="flex items-center justify-center h-full text-gray-400">
            Loading heatmap data...
          </div>

          <VueApexCharts
            v-else-if="heatmapSeries.length > 0"
            :key="chartKey"
            type="heatmap"
            width="100%"
            height="400"
            :options="heatmapOptions"
            :series="heatmapSeries"
          />

          <div v-else class="flex items-center justify-center h-full text-gray-400">
            No session data available for the selected filters.
          </div>
        </div>
      </div>
    </div>

    <div class="w-1/2 flex flex-col">
      <div class="p-4 border-b border-gray-600">
        <div class="w-full h-48 flex flex-row items-center justify-center">
          <VueApexCharts
            v-if="radialChartData.series.length"
            type="radialBar"
            width="200"
            height="200"
            :options="radialOptions"
            :series="radialChartData.series"
          />
          
          <div v-else-if="isLoadingDetails" class="text-gray-400 mt-2">Loading session details...</div>
          <div v-else class="text-gray-400 mt-2">Select a session to view accuracy</div>

        </div>
        <div
          v-for="(label, index) in radialChartData.labels"
          :key="index"
          :class="[
            'cursor-pointer',
            {
              'font-bold text-blue-400':
                hoveredPlayerIndex === index ||
                (selectedPlayerIndex === index && hoveredPlayerIndex === null) ||
                (selectedPlayerIndex === null && hoveredPlayerIndex === null && index === 0),
              'text-gray-400': radialChartData.rawScores[index] === 0
            }
          ]"
          @mouseover="hoveredPlayerIndex = index"
          @mouseleave="hoveredPlayerIndex = null"
          @click="selectedPlayerIndex = index"
        >
          {{ label }} — {{ radialChartData.rawScores[index] || 0 }} pts
        </div>
      </div>

      <div class="p-4 overflow-auto">
        <div v-if="selectedSessionDetails">
          <div class="space-y-2 text-sm">
            
            <div class="mt-4">

              <template v-if="selectedSessionDetails.questions && selectedSessionDetails.questions.length > 0">
                <div v-for="question in selectedSessionDetails.questions" :key="question.question_number">
                  <div class="font-medium">
                    Q{{ question.question_number }}: {{ question.question_text }}
                  </div>

                  <div v-for="answer in question.player_answers" :key="answer.player_name" class="mt-1">
                    <span class="text-blue-300">{{ answer.player_name }}: {{ answer.submitted || 'No answer' }}</span>
                    <span :class="answer.is_correct ? 'text-green-400' : 'text-red-400'" class="ml-2">
                      {{ answer.is_correct ? '✓' : '✗' }}
                    </span>
                    <span class="ml-2 text-gray-300">({{ answer.score_awarded || 0 }} pts)</span>
                  </div>

                  <div v-if="props.showAiScores && question.ai_answer" class="mt-1">
                    <span class="text-green-300">AI: {{ question.ai_answer }}</span>
                    <span :class="question.ai_is_correct ? 'text-green-400' : 'text-red-400'" class="ml-2">
                      {{ question.ai_is_correct ? '✓' : '✗' }}
                    </span>
                    <span class="ml-2 text-gray-300">({{ question.ai_score || 0 }} pts)</span>
                  </div>
                </div>
              </template>

              <template v-else>
                <div class="text-gray-400 text-sm">
                  No detailed questions found for this session. This might be due to:
                  <ul class="list-disc list-inside mt-1 text-xs">
                    <li>Backend API not returning question details for this game type</li>
                    <li>Session data structure differences</li>
                    <li>Data not stored for this particular session</li>
                  </ul>
                </div>
              </template>
              
            </div>
          </div>
        </div>

        <div v-else class="text-gray-400">
          Click a heatmap cell to view session details.
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>

.selected-cell {
  stroke: #facc15 !important; /* yellow-400 */
  stroke-width: 3 !important;
}

/* Selection stroke for heatmap cells - multiple approaches for better compatibility */
:deep(.apexcharts-heatmap-rect.selected-cell) {
  stroke: #ffffff !important;
  stroke-width: 3px !important;
  stroke-opacity: 1 !important;
  filter: drop-shadow(0 0 0 2px #ffffff) !important;
}

/* Alternative approach with box-shadow effect */
:deep(.apexcharts-series .apexcharts-heatmap-rect.selected-cell) {
  stroke: #ffffff !important;
  stroke-width: 3px !important;
  stroke-opacity: 1 !important;
  box-shadow: 0 0 0 2px #ffffff !important;
}

/* Ensure the stroke is visible over other elements */
:deep(.selected-cell) {
  z-index: 1000 !important;
}

/* Alternative visual indicator using outline if stroke doesn't work */
:deep(.apexcharts-heatmap-rect.selected-cell) {
  outline: 3px solid #ffffff !important;
  outline-offset: -1px !important;
}

/* Custom scrollbar for session details */
.overflow-y-auto::-webkit-scrollbar {
  width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #374151;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #6B7280;
  border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #9CA3AF;
}
</style>