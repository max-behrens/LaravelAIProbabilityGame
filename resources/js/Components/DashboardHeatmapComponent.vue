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
  { from: 0, to: 0, color: '#1f2937', name: 'No Activity' },
  { from: 1, to: 25, color: '#1a346e', name: '1-25 pts' },
  { from: 26, to: 50, color: '#3b82f6', name: '26-50 pts' },
  { from: 51, to: 75, color: '#60a5fa', name: '51-75 pts' },
  { from: 76, to: 100, color: '#1e40af', name: '76-100 pts' },
  { from: 101, to: 1000, color: '#1e3a8a', name: '100+ pts' }
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
const heatmapSeries = computed(() => {
  const dates = currentWindowDates.value;
  
  // Group and sort sessions by date
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
      const scoreValue = session?.combined_score ?? null;

      // Note: We are no longer setting fillColor directly here because ApexCharts' colorScale.ranges takes precedence.
      // The logic for coloring will now be entirely driven by `activeHeatmapColorRanges`.
      
      return {
        x: date,
        y: scoreValue,
        sessionId: session?.session_id ?? null,
        players: session?.players ?? [],
        gameName: session?.game_name ?? null,
        session: session,
        createdAt: session?.created_at ?? null,
        hasAiScore: session?.ai_score !== null && session?.ai_score !== undefined,
        // fillColor: fillColor // This line is no longer effectively used for coloring
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

// Heatmap chart options
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
            gameName: selected.gameName,
            score: selected.y
          };
          await fetchSessionDetails(selected.sessionId);
        }
      }
    }
  },
  plotOptions: {
    heatmap: {
      enableShades: true,
      distributed: false,
      colorScale: {
        ranges: activeHeatmapColorRanges.value // Use the computed color ranges here
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

      // Build players list for tooltip
      const playersHtml = session.players.map(player => 
        `<div style="margin-left: 10px; font-size: 11px;">
          ${player.player_name}: ${player.total_score} pts
        </div>`
      ).join('');

      const aiIndicator = cellData.hasAiScore 
        ? '<span style="color: #10b981; font-size: 10px;">🤖 AI Present</span><br/>'
        : '';

      return `
        <div style="padding:8px; background-color:#1e1e1e; color:#cccccc; border-radius:4px; max-width:300px;">
          ${aiIndicator}
          <strong>Game:</strong> ${session.game_name}<br/>
          <strong>Date:</strong> ${cellData.x}<br/>
          <strong>Time:</strong> ${time}<br/>
          <strong>Players:</strong><br/>
          ${playersHtml}
          <div style="margin-top: 4px; font-size: 8pt; color: #888;">
            Session: ${session.truncated_session_id}
          </div>
        </div>
      `;
    }
  },
  states: {
    normal: { filter: { type: 'none' } },
    hover: { filter: { type: 'none' } },
    active: { filter: { type: 'none' } }
  }
});

// Update chart options on data change
watch([heatmapSeries, activeHeatmapColorRanges], () => { // Watch activeHeatmapColorRanges
  heatmapOptions.value = {
    ...heatmapOptions.value,
    xaxis: {
      ...heatmapOptions.value.xaxis,
      categories: heatmapXAxisCategories.value
    },
    plotOptions: {
      heatmap: {
        ...heatmapOptions.value.plotOptions.heatmap,
        colorScale: {
          ranges: activeHeatmapColorRanges.value // Ensure color ranges are updated
        }
      }
    }
  };
  
  // Force chart re-render when data or color ranges change
  chartKey.value++;
}, { deep: true });

// Separate watcher for showAiScores to trigger re-render
watch(() => props.showAiScores, () => {
  chartKey.value++;
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
          color: '#ffffff',
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



// Watch filters props to refetch heatmap data
watch(
  [() => props.gameTypeId, () => props.startDate, () => props.endDate, () => props.userId, () => props.showAiScores],
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
                  : 'bg-gray-600 text-gray-400'
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
                  : 'bg-gray-600 text-gray-400'
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
                  : 'bg-gray-600 text-gray-400'
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
                  : 'bg-gray-600 text-gray-400'
              ]"
            >
              End ⟫
            </button>
          </div>
        </div>

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