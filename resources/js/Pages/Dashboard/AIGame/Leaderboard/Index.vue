<script setup>
import { ref, watchEffect, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import GameAuthenticatedLayout from '@/Layouts/GameAuthenticated.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import { useGames } from '@/Composables/useGames';
import DynamicPagination from '@/Components/DynamicPagination.vue';
import axios from 'axios';

const props = defineProps({
  games: {
    type: Array,
    default: () => []
  },
});

const gameScores = props.gameScores;

</script>

<template>
  <Head title="Leaderboard" />

  <BreezeAuthenticatedLayout>
    <GameAuthenticatedLayout>

        <div class="py-4 mb-6">
            <div class="main-width mx-auto sm:px-6 lg:px-8">

                <div class="flex-1 min-w-[300px] p-4 bg-gray-800 rounded shadow">
                    <h3 class="font-semibold text-lg mb-2">Player Scores</h3>
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-700">
                                <th class="p-2 border-b">Player</th>
                                <th class="p-2 border-b">Game Session</th>
                                <th class="p-2 border-b">Difficulty</th>
                                <th class="p-2 border-b">Category</th>
                                <th class="p-2 border-b">Score</th>
                                <th class="p-2 border-b">Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="score in gameScores" :key="score.id">
                                <td class="p-2 border-b text-white">{{ score.user?.name }}</td>
                                <td class="p-2 border-b text-white">{{ score.session_id }}</td>
                                <td class="p-2 border-b text-white">
                                    {{ score.answer_json?.difficulty_name || score.answer_json?.difficulty_name || 'N/A' }}
                                </td>
                                <td class="p-2 border-b text-white">
                                    {{ score.answer_json?.category_name || score.answer_json?.category_name || 'N/A' }}
                                </td>
                                <td class="p-2 border-b text-white">{{ score.score }}</td>
                                <td class="p-2 border-b text-white">{{ formatDate(score.created_at) }}</td>
                            </tr>
                            <tr v-if="!gameScores">
                                <td colspan="6" class="p-2 text-center text-gray-400">No scores available</td>
                            </tr>
                        </tbody>
                    </table>
                    <DynamicPagination :currentPage="scoresCurrentPage" :totalPages="scoresTotalPages"
                        @change-page="changeScoresPage" />
                </div>
            </div>
        </div>

     
    </GameAuthenticatedLayout>
  </BreezeAuthenticatedLayout>
</template>