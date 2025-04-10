<script setup>
import { ref } from 'vue'; // Importing Vue composables
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated.vue";

// Declare reactive state to hold the timestamps
const timestamps = ref([]); 

// Function to fetch timestamps from the backend
const getTimestamps = async () => {
  try {
    const description = 'Intrusion ended'; // Example description to send
    const { data } = await axios.get('/api/parse-xml/timestamps', { params: { description } });
    timestamps.value = data; // Update state with the fetched data
  } catch (error) {
    console.error('Error fetching timestamps:', error); // Error handling
  }
};
</script>

<style scoped>
/* Additional custom styles can be added here */
</style>



<template>
    <Head title="XML Parser" /> <!-- Change the title to XML Parser -->
  
    <!-- Using BreezeAuthenticatedLayout with a custom header slot -->
    <BreezeAuthenticatedLayout>
      <template #header>
        <h2 class="text-md font-semibold leading-tight text-white"> XML Parser </h2>
        </template>
  
      <!-- Main content of the page -->
      <div class="py-12">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">

            <div class="shadow-sm sm:rounded-lg">
                    <div class="p-8 border-b border-gray-200">
                        <div class="relative">
                        
                            <!-- Button to trigger the getTimestamps function -->
                            <button 
                            @click="getTimestamps" 
                            class="bg-blue-500 text-white font-semibold py-2 px-4 rounded hover:bg-blue-600"
                            >
                            Get Timestamps
                            </button>
                    
                            <!-- Conditional rendering of the timestamps if available -->
                            <div v-if="timestamps.length" class="mt-4">
                                <h3 class="text-lg font-medium text-white">Matching Timestamps:</h3>
                                <ul class="list-disc pl-5 space-y-2">
                                    <li 
                                    v-for="timestamp in timestamps" 
                                    :key="timestamp" 
                                    class="text-gray-700"
                                    >
                                    {{ timestamp }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
      </div>
    </BreezeAuthenticatedLayout>
  </template>
  
  