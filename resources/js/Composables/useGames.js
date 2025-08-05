import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Pusher from 'pusher-js';

export function useGames() {
  const games = ref([]);
  const gameScores = ref([]);
  const error = ref(null);
  
  // Initialize pagination metadata with proper defaults
  const paginationMeta = ref({
    total: 0,
    perPage: 10,
    currentPage: 1,
    lastPage: 1
  });

  // Scores metadata
  const scoresMetadata = ref({
    total: 0,
    perPage: 10,
    currentPage: 1,
    lastPage: 1
  });

  let pusher = null;
  let generalChannel = null;

  const fetchGames = async (page = 1, filters = {}) => {
    try {
      error.value = null; // Clear previous errors
      
      const params = { page, ...filters };
      const response = await axios.get('/dashboard/games', { params });

      // Safely assign games data
      if (response.data) {
        games.value = response.data.data || [];

        // Update pagination metadata safely
        if (response.data.meta) {
          paginationMeta.value = {
            total: response.data.meta.total || 0,
            perPage: response.data.meta.per_page || 10,
            currentPage: response.data.meta.current_page || 1,
            lastPage: response.data.meta.last_page || 1
          };
        } else if (response.data.last_page !== undefined) {
          // Handle alternative Laravel pagination structure
          paginationMeta.value = {
            total: response.data.total || 0,
            perPage: response.data.per_page || 10,
            currentPage: response.data.current_page || 1,
            lastPage: response.data.last_page || 1
          };
        }
      }
    } catch (err) {
      console.error('Error fetching games:', err);
      error.value = err;
      // Ensure we don't leave games in an undefined state
      if (!games.value) {
        games.value = [];
      }
    }
  };

  const fetchGameScores = async (gameId, page = 1) => {
    try {
      error.value = null; // Clear previous errors
      
      const response = await axios.get(`/games/${gameId}/scores`, {
        params: { page },
      });

      // Safely assign scores data
      if (response.data) {
        gameScores.value = response.data.data || [];
        
        // Update scores metadata safely
        if (response.data.meta) {
          scoresMetadata.value = {
            total: response.data.meta.total || 0,
            perPage: response.data.meta.per_page || 10,
            currentPage: response.data.meta.current_page || 1,
            lastPage: response.data.meta.last_page || 1
          };
        } else if (response.data.last_page !== undefined) {
          scoresMetadata.value = {
            total: response.data.total || 0,
            perPage: response.data.per_page || 10,
            currentPage: response.data.current_page || 1,
            lastPage: response.data.last_page || 1
          };
        }
      }
    } catch (err) {
      console.error('Error fetching game scores:', err);
      error.value = err;
      // Ensure we don't leave scores in an undefined state
      if (!gameScores.value) {
        gameScores.value = [];
      }
    }
  };

  const setupPusher = () => {
    try {
      pusher = new Pusher('c493e35de663a696d88e', {
        cluster: 'eu',
        encrypted: true,
      });

      generalChannel = pusher.subscribe('games');
      generalChannel.bind('game.updated', async () => {
        // Add error handling for Pusher callbacks
        try {
          await fetchGames();
        } catch (err) {
          console.error('Error refreshing games from Pusher:', err);
        }
      });
    } catch (err) {
      console.error('Error setting up Pusher:', err);
    }
  };

  const cleanup = () => {
    try {
      if (generalChannel) {
        generalChannel.unbind_all();
      }
      if (pusher) {
        pusher.unsubscribe('games');
        pusher.disconnect();
      }
    } catch (err) {
      console.error('Error during cleanup:', err);
    }
  };

  onMounted(() => {
    fetchGames(); // Fetch games initially
    setupPusher();
  });

  onUnmounted(() => {
    cleanup();
  });

  return {
    games,
    gameScores,
    scoresMetadata,
    paginationMeta,
    fetchGames,
    fetchGameScores,
    error,
  };
}