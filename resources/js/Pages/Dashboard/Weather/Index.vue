<script setup>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated.vue";
import { Head } from "@inertiajs/inertia-vue3";
import BreezeButton from "@/Components/Button.vue";
import Pagination from "@/Components/Pagination.vue";
import SortArrowUp from "@/Components/SortArrowUp.vue";
import SortArrowDown from "@/Components/SortArrowDown.vue";
import { Inertia } from "@inertiajs/inertia";
import { useForm } from "@inertiajs/inertia-vue3";
import { usePage } from '@inertiajs/inertia-vue3';
import WeatherChartComponent from '@/Components/WeatherChartComponent.vue';
import { Link } from "@inertiajs/inertia-vue3";
import axios from 'axios';
import { ref, reactive, watch, onMounted } from 'vue';

// Reactive variables
const city = ref('');  // City name for weather
const weatherData = ref(null);  // Weather data object
const forecastData = ref(null);  // Forecast data object
const aiResponseResults = ref({
    temperatureExplanation: '**',  // Default placeholder
    humidityExplanation: '**',
    pressureExplanation: '**'
});
const calculationResults = ref(null);  // For storing errors
const errorMessage = ref(null);  // For storing errors
const userInput = ref('');  // Input for the chatbot
const aiResponse = ref('');  // AI response for the chatbot
const isChatbotOpen = ref(false);  // Toggle for chatbot
const chatbotInput = ref('');  // User input for chatbot
const chatbotMessages = ref([]);  // Store chat messages

const loading = ref(false);


const form = useForm();

// Method to save dialogue
function saveDialogue() {
    // Ensure the necessary data is available
    const dialogueData = {
        calculationResults: calculationResults.value,
        aiResponseResults: aiResponseResults.value,
        chatbotMessages: chatbotMessages.value
    };

    console.log('dialogueData: ', dialogueData);

    // Send the data to the backend to create the post
    axios.get(route('posts.create'), {
        title: 'Some title',  // You can provide a title here
        content: 'Some content',  // You can provide content here
        calculationResults: dialogueData.calculationResults,
        aiResponseResults: dialogueData.aiResponseResults,
        chatbotMessages: dialogueData.chatbotMessages
    })
    .then(response => {
        // Do something with the response if necessary
        console.log('Post created with response:', response);
    })
    .catch(error => {
        console.error('Error creating post:', error);
    });
}




// Toggle chatbot visibility
const toggleChatbot = () => {
    isChatbotOpen.value = !isChatbotOpen.value;
};

// Send message to chatbot
const sendMessage = async () => {
    if (chatbotInput.value.trim() === "") return;
    
    const userMessage = chatbotInput.value;
    chatbotMessages.value.push({ text: userMessage, isUser: true });

    try {
        const calculationData = weatherData.value || {};  // Include weather data if available
        await askAI(true, city.value, calculationData);
        chatbotMessages.value.push({ text: aiResponse.value, isUser: false });
        chatbotInput.value = '';  // Clear input after sending

        // Open chatbot if it's closed
        if (!isChatbotOpen.value) {
            toggleChatbot(); // This ensures the chatbot opens once a message is sent
        }
    } catch (error) {
        chatbotMessages.value.push({ text: "Error: Unable to get AI response.", isUser: false });
        console.error("Chatbot AI Error:", error);
    }
};

// Request AI response
const chatbotAIResponse = ref(''); // New variable for chatbot messages

const askAI = async (isUserInitiated = false, cityName = '', calculationResults = {}) => {
    if (!isUserInitiated && !chatbotInput.value.trim()) return;

    try {
        let requestData = isUserInitiated
            ? { user_input: chatbotInput.value, city: cityName, calculationResults }
            : { weather_explanations: calculationResults, city: cityName };

        console.log('requestData: ', requestData);

        const { data } = await axios.post('/api/ask-openai', requestData);

        // ✅ Separate chatbot AI response from weather explanations
        if (isUserInitiated) {
            chatbotAIResponse.value = data.aiResponse; // Store chatbot AI response separately
            chatbotMessages.value.push({ text: chatbotAIResponse.value, isUser: false });
        } else {
          aiResponseResults.value = {
              temperatureExplanation: data.temperatureExplanation || aiResponseResults.value.temperatureExplanation,
              humidityExplanation: data.humidityExplanation || aiResponseResults.value.humidityExplanation,
              pressureExplanation: data.pressureExplanation || aiResponseResults.value.pressureExplanation,
          };
        }

        console.log('chatbotAIResponse.value:', chatbotAIResponse.value);
        console.log('aiResponseResults:', aiResponseResults.value);
    } catch (error) {
        console.error('Error fetching AI response:', error);

        if (isUserInitiated) {
            chatbotAIResponse.value = 'Error: Unable to get a response from AI.';
            chatbotMessages.value.push({ text: chatbotAIResponse.value, isUser: false });
        }
    }
};






// Props from parent component
const props = defineProps({
  weather: {
    type: Object,
    default: () => ({}),
  },
  posts: {
      type: Object,
      default: () => ({}),
  },
  postId: {
    type: [String, Number], // Define the type based on the expected type
    default: null,           // Default to null if not passed
  },
  filters: {
    type: Object,
    default: () => ({}),
  }
});


// Utility function to get current formatted system time
function getCurrentTime() {
  const now = new Date();
  const options = {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  };
  return new Intl.DateTimeFormat('en-GB', options).format(now);
}

// Format date utility function
function formatDate(dateString) {
  const date = new Date(dateString);
  
  // Add ordinal suffix to day
  const day = date.getDate();
  const suffix = getDaySuffix(day);
  
  // Get month abbreviation
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const month = months[date.getMonth()];
  
  // Format full date
  return `${day}${suffix} ${month} ${date.getFullYear()}`;
}

// Helper function to get day suffix (th, st, nd, rd)
function getDaySuffix(day) {
  if (day > 3 && day < 21) return 'th';
  switch (day % 10) {
    case 1: return 'st';
    case 2: return 'nd';
    case 3: return 'rd';
    default: return 'th';
  }
}

const params = reactive({
  city: props.filters.city,
  field: props.filters.field,
  direction: props.filters.direction,
});

// Watch for changes in the `params`
watch(params, () => {


});

const displayCity = ref('');


// Fetch weather data method
async function fetchWeather() {
  errorMessage.value = null;  // Clear previous error message
  loading.value = true; // ✅ Start spinner


  try {
    if (!city.value) {
      errorMessage.value = 'Please enter a city name';
      return;
    }

    console.log('HERE 1');

    const response = await axios.post(route('weather.getData'), { city: city.value, postId: props.postId });

    console.log('HERE 2');

    console.log('response: ', response);

    displayCity.value = city.value;


    weatherData.value = response.data;
    forecastData.value = response.data.forecasts.map(forecast => {
      // Increment the forecast's date by one day
      const forecastDate = new Date(forecast.time);
      forecastDate.setDate(forecastDate.getDate() + 1);  // Add 1 day

      return {
        ...forecast,
        formattedTime: formatDate(forecastDate), // Update the formatted time after shifting the date
      };
    });

    console.log('HERE 3');

    // ✅ Ensure `calculationResults` is set before calling `askAI`
    calculationResults.value = {
      city: city.value + ',',
      temperatureChanges: response.data.temperatureChanges,
      humidityChanges: response.data.humidityChanges,
      pressureChanges: response.data.pressureChanges,
      averageTemperatureChanges: response.data.averageTemperatureChanges,
      averageHumidityChanges: response.data.averageHumidityChanges,
      averagePressureChanges: response.data.averagePressureChanges,
    };

    aiResponseResults.value = {
      temperatureExplanation: response.data.temperatureExplanation,
      humidityExplanation: response.data.humidityExplanation,
      pressureExplanation: response.data.pressureExplanation,
    };

    console.log('HERE 5');

    // Add city to URL query parameter
    updateUrlWithCity(city.value);

    // ✅ Only call AI if `calculationResults` is not empty
    if (calculationResults.value) {
      await askAI(false, city.value, calculationResults.value); // Pass `calculationResults` to `askAI`
      chatbotMessages.value.push({ text: aiResponse.value, isUser: false });
    }

  } catch (error) {
    errorMessage.value = 'City not recognized. Please enter a valid city name';
    weatherData.value = null;
    forecastData.value = null;
    calculationResults.value = null;  // Reset it to `null` in case of error
    aiResponseResults.value = null;
    console.error(error);

  } finally {
    loading.value = false; // ✅ Stop spinner
  }
}

// Function to update URL with city query parameter
function updateUrlWithCity(cityName) {
  if (!cityName) return;
  
  // Create a URL object with the current URL
  const url = new URL(window.location.href);
  
  // Set the city query parameter
  url.searchParams.set('city', cityName);
  
  // Update the URL without reloading the page
  window.history.pushState({}, '', url.toString());
}



// Set city input method
function setCityInput(input) {
  params.city = input;
  city.value = input; // Update the reactive city variable
}


</script>


<template>
    <Head title="Weather" />
    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="text-md font-semibold leading-tight text-white-800"> Weather API </h2>
        </template>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="$page.props.flash.message" class="alert alert-success shadow-lg mb-5">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $page.props.flash.message }}</span>
                    </div>
                </div>
                <div class="shadow-sm sm:rounded-lg">
                    <div class="p-8 border-b border-gray-200">
                        <div class="relative">
                            <div class="mt-6 mb-6">
                                <label for="default-search" class="mb-2 text-sm font-medium text-white-900 sr-only dark:text-white-300">Weather API</label>
                            </div>
                            <div class="overflow-x-clip">
                                <!-- Error message display -->
                                <div v-if="errorMessage" class="error-message">{{ errorMessage }}</div>

                                <!-- Weather Search Input -->
                                <div class="weather-search">
                                    <input id="weather-search-input" type="text" v-debounce:300="setCityInput" class="input w-full max-w-xs placeholder-white text-white" placeholder="Enter City..."/>

                                     <!-- Spinner shown while loading -->
                                    <div v-if="loading" class="flex items-center">
                                        <svg class="animate-spin h-5 w-5 mt-3 text-accent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                        </svg>
                                    </div>
                                    
                                    <button @click="fetchWeather" class="fetch-button mt-4 btn btn-accent block max-w-ws"><strong>Get Weather</strong></button>
                                </div>

                                <div class="block md:block lg:overflow-x-auto">


                                  <!-- Mobile View -->
                                  <div class="block md:hidden">
                                      <!-- Weather Forecast Results -->
                                      <div v-if="forecastData" class="mt-10">
                                          <h3 class="text-lg font-semibold mb-4 text-center">Daily Average Forecast for {{ displayCity }}</h3>
                                          <div class="grid grid-cols-1 gap-4">
                                              <div v-for="(forecast, index) in forecastData" :key="index" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg">
                                                  <h4 class="text-md font-semibold mb-2">{{ forecast.formattedTime }}</h4>
                                                  <p><strong>Temperature:</strong> {{ forecast.temperature.toFixed(2) }}°C</p>
                                                  <p><strong>Feels Like:</strong> {{ forecast.feels_like.toFixed(2) }}°C</p>
                                                  <p><strong>Humidity:</strong> {{ forecast.humidity }}%</p>
                                                  <p><strong>Pressure:</strong> {{ forecast.pressure }} hPa</p>
                                              </div>
                                          </div>
                                      </div>

                                      <!-- Weather Calculation Results -->
                                      <div v-if="calculationResults" class="mt-10">
                                          <h3 class="text-lg font-semibold mb-4 text-center">Weather Fluctuation Values</h3>

                                          <div class="grid grid-cols-1 gap-4">
                                              <div v-if="calculationResults.temperatureChanges" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg">
                                                  <h4 class="font-semibold">Temperature Changes</h4>
                                                  <p>Rate of Change Between Each Day: {{ calculationResults.temperatureChanges.join(', ') }}</p>
                                                  <p>Overall Average Rate of Change: {{ calculationResults.averageTemperatureChanges }}</p>
                                              </div>

                                              <div v-if="calculationResults.humidityChanges" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg">
                                                  <h4 class="font-semibold">Humidity Changes</h4>
                                                  <p>Rate of Change Between Each Day: {{ calculationResults.humidityChanges.join(', ') }}</p>
                                                  <p>Overall Average Rate of Change: {{ calculationResults.averageHumidityChanges }}</p>
                                              </div>

                                              <div v-if="calculationResults.pressureChanges" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg">
                                                  <h4 class="font-semibold">Pressure Changes</h4>
                                                  <p>Rate of Change Between Each Day: {{ calculationResults.pressureChanges.join(', ') }}</p>
                                                  <p>Overall Average Rate of Change: {{ calculationResults.averagePressureChanges }}</p>
                                              </div>
                                          </div>
                                      </div>

                                      <!-- AI-Generated Weather Insights -->
                                      <div v-if="aiResponseResults && aiResponseResults.temperatureExplanation !== '**'" class="mt-10">
                                          <h3 class="text-lg font-semibold mb-4 text-center">Fluctuation Data Contextualised by OpenAI</h3>

                                          <div class="grid grid-cols-1 gap-4">
                                              <div v-if="aiResponseResults.temperatureExplanation" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg">
                                                  <h4 class="font-semibold">Temperature</h4>
                                                  <p>{{ aiResponseResults.temperatureExplanation }}</p>
                                              </div>

                                              <div v-if="aiResponseResults.humidityExplanation" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg">
                                                  <h4 class="font-semibold">Humidity</h4>
                                                  <p>{{ aiResponseResults.humidityExplanation }}</p>
                                              </div>

                                              <div v-if="aiResponseResults.pressureExplanation" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg">
                                                  <h4 class="font-semibold">Pressure</h4>
                                                  <p>{{ aiResponseResults.pressureExplanation }}</p>
                                              </div>
                                          </div>
                                      </div>
                                  </div>







                                  <!-- Desktop View -->

                                  <!-- Weather Forecast Results -->
                                <div class="hidden md:block overflow-x-auto">
                                  <div v-if="forecastData" class="mt-10">
                                    <h3 class="text-sm font-semibold mb-4 text-center">Daily Average Forecast for {{ displayCity }}</h3>
                                    <div class="flex space-x-4 overflow-x-auto justify-between">
                                      <div v-for="(forecast, index) in forecastData" :key="index" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative w-64">
                                        <h4 class="text-sm font-semibold mb-2">{{ forecast.formattedTime }}</h4>
                                        <p><strong>Temperature:</strong> {{ forecast.temperature.toFixed(2) }}°C</p>
                                        <p><strong>Feels Like:</strong> {{ forecast.feels_like.toFixed(2) }}°C</p>
                                        <p><strong>Humidity:</strong> {{ forecast.humidity }}%</p>
                                        <p><strong>Pressure:</strong> {{ forecast.pressure }} hPa</p>
                                      </div>
                                    </div>
                                  </div>

                                  <!-- Weather Calculation Results -->
                                  <div v-if="calculationResults" class="mt-20">
                                    <h3 class="text-sm font-semibold mb-4 text-center">Weather Fluctuation Values</h3>

                                    <!-- Group temperature, humidity, and pressure changes -->
                                    <div class="flex space-x-4 overflow-x-auto justify-between">
                                        <div v-if="calculationResults.temperatureChanges" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative">
                                            <h4 class="font-semibold">Temperature Changes</h4>
                                            <p>Rate of Change Between Each Day: {{ calculationResults.temperatureChanges.join(', ') }}</p>
                                            <p>Overall Average Rate of Change: {{ calculationResults.averageTemperatureChanges }}</p>
                                        </div>

                                        <div v-if="calculationResults.humidityChanges" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative">
                                            <h4 class="font-semibold">Humidity Changes</h4>
                                            <p>Rate of Change Between Each Day: {{ calculationResults.humidityChanges.join(', ') }}</p>
                                            <p>Overall Average Rate of Change: {{ calculationResults.averageHumidityChanges }}</p>
                                        </div>

                                        <div v-if="calculationResults.pressureChanges" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative">
                                            <h4 class="font-semibold">Pressure Changes</h4>
                                            <p>Rate of Change Between Each Day: {{ calculationResults.pressureChanges.join(', ') }}</p>
                                            <p>Overall Average Rate of Change: {{ calculationResults.averagePressureChanges }}</p>
                                        </div>
                                    </div>
                                  </div>


                                  <!-- AI-Generated Weather Insights -->
                                  <div v-if="aiResponseResults && Object.keys(aiResponseResults).length > 0 && aiResponseResults.temperatureExplanation !== '**'" class="mt-10">
                                    <h3 class="text-sm font-semibold mb-4 text-center">Fluctuation Data Contextualised By OpenAI</h3>
                                      <div class="flex space-x-4 overflow-x-auto justify-between">
                                          <div v-if="aiResponseResults.temperatureExplanation" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                                              <h4 class="font-semibold">Temperature</h4>
                                              <p>{{ aiResponseResults.temperatureExplanation }}</p>
                                          </div>
                                          <div v-if="aiResponseResults.humidityExplanation" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                                              <h4 class="font-semibold">Humidity</h4>
                                              <p>{{ aiResponseResults.humidityExplanation }}</p>
                                          </div>
                                          <div v-if="aiResponseResults.pressureExplanation" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                                              <h4 class="font-semibold">Pressure</h4>
                                              <p>{{ aiResponseResults.pressureExplanation }}</p>
                                          </div>
                                      </div>
                                  </div>
                                </div>


                                <!-- ✅ Always Visible Chatbot Widget -->
                                <div class="top-5 right-5 mt-12 z-50">
                                    <!-- Chatbot Toggle Button -->
                                    <button @click="toggleChatbot" class="bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg">
                                        💬 Chat with AI
                                    </button>

                                    <!-- Chatbot Window -->
                                    <div v-if="isChatbotOpen" class="border border-gray-300 shadow-lg rounded-lg p-4 w-full">
                                        <div class="flex justify-between items-center border-b pb-2">
                                            <h4 class="text-lg font-semibold">Chatbot</h4>
                                            <button @click="toggleChatbot" class="text-red-500">✖</button>
                                        </div>
                                        
                                        <!-- Chat Messages -->
                                        <div class="chatbot-messages h-60 overflow-y-auto p-2">
                                            <div v-for="(message, index) in chatbotMessages" :key="index" class="mb-2">
                                                <div :class="message.isUser ? 'text-right' : 'text-left'">
                                                    <span :class="message.isUser ? 'bg-blue-200' : 'bg-gray-200'" class="text-gray-900 inline-block px-3 py-2 rounded">
                                                        {{ message.text }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Chat Input Field -->
                                        <div class="mt-2 flex">
                                            <input v-model="chatbotInput" type="text" class="border px-2 py-1 flex-1 rounded-l placeholder-white text-white" placeholder="Type a message..." />
                                            <button @click="sendMessage" class="bg-blue-500 text-white px-3 py-1 rounded-r">Send</button>
                                        </div>
                                    </div>
                                </div>


                              </div>
                              <div v-if="calculationResults" class="mt-20 mb-20">
                                <h3 class="text-sm font-semibold mb-4 text-center">Weather Data Visuals</h3>
                                <WeatherChartComponent v-if="calculationResults" :calculationResults="calculationResults" />


                               <!-- Save Dialogue Button -->
                              <div class="mt-10">
                                  <Link
                                      class="btn btn-accent"
                                        :href="route('posts.create', { 
                                            calculationResults: JSON.stringify(calculationResults), 
                                            aiResponseResults: JSON.stringify(aiResponseResults), 
                                            chatbotMessages: JSON.stringify(chatbotMessages) 
                                        })"
                                  >
                                      Save Dialogue
                                  </Link>
                              </div>

                              </div>


                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>