<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { ref } from 'vue';
import GameGraphComponent from '@/Components/GameGraphComponent.vue';

const playerCount = ref("1");
const playAgainstAI = ref(false);

// Filler player data (same as before)
const players = [
    { name: 'Alice', score: 120 },
    { name: 'Bob', score: 95 },
    { name: 'Charlie', score: 110 },
];

const handlePlay = () => {
    console.log(`Playing with ${playerCount.value} player(s), against AI: ${playAgainstAI.value}`);
};
</script>

<template>
    <Head title="AI Game Dashboard" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-md text-white leading-tight">AI Game Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Flex Container -->
                <div class="flex flex-wrap gap-4 justify-center items-center">

                    <!-- GameGraphComponent -->
                    <div class="flex-1 w-full sm:w-96 md:w-80 lg:w-96 p-4 bg-gray-800 rounded shadow">
                        <GameGraphComponent />
                    </div>

                    <!-- Player Table -->
                    <div class="flex-1 w-full sm:w-96 md:w-80 lg:w-96 p-4 bg-gray-800 rounded shadow overflow-y-auto">
                        <h3 class="font-semibold text-lg mb-2 text-white">Player Scores</h3>
                        <table class="w-full text-left border-collapse text-white">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="p-2 border-b">Player</th>
                                    <th class="p-2 border-b">Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="player in players" :key="player.name">
                                    <td class="p-2 border-b">{{ player.name }}</td>
                                    <td class="p-2 border-b">{{ player.score }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bottom Controls (Dropdown, Checkbox, Button) -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center w-full sm:w-auto p-4 bg-gray-800 rounded shadow">
                        <!-- Dropdown -->
                        <div>
                            <label for="players" class="mr-2 font-medium text-white">Number of Players:</label>
                            <select id="players" v-model="playerCount" class="border rounded px-2 py-1 bg-gray-700 text-white">
                                <option value="1">1 Player</option>
                                <option value="2">2 Players</option>
                            </select>
                        </div>

                        <!-- Checkbox -->
                        <div>
                            <label class="inline-flex items-center text-white">
                                <input type="checkbox" v-model="playAgainstAI" class="mr-2">
                                <span>Play against AI</span>
                            </label>
                        </div>

                        <!-- Play button -->
                        <div>
                            <button @click="handlePlay" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                Play
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>
