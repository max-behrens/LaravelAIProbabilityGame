import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Pusher from 'pusher-js';

export function useGames() {
  const games = ref([]);
  const gameScores = ref([]); // Scores data
  const scoresMetadata = ref({ // Add metadata for pagination
    total: 0,
    perPage: 10,
    currentPage: 1,
    lastPage: 1
  });
  const error = ref(null);
  let pusher = null;
  let generalChannel = null;

  const fetchGames = async (page = 1) => {
    try {
      const response = await axios.get('/dashboard/games', {
        params: { page },
      });

      // Assign games data and preserve pagination metadata
      games.value = response.data.data || [];
      
      // Store pagination metadata if available
      if (response.data.meta || response.data.links) {
        // Handle Laravel pagination metadata structure
      }
    } catch (err) {
      error.value = err;
    }
  };

  const fetchGameScores = async (gameId, page = 1) => {
    try {
      const response = await axios.get(`/games/${gameId}/scores`, {
        params: { page },
      });

      // Assign scores data
      gameScores.value = response.data.data || [];
      
      // Store pagination metadata
      if (response.data.meta) {
        scoresMetadata.value = {
          total: response.data.meta.total || 0,
          perPage: response.data.meta.per_page || 10,
          currentPage: response.data.meta.current_page || 1,
          lastPage: response.data.meta.last_page || 1
        };
      } else if (response.data.last_page) {
        // Alternative structure some Laravel APIs might use
        scoresMetadata.value = {
          total: response.data.total || 0,
          perPage: response.data.per_page || 10,
          currentPage: response.data.current_page || 1,
          lastPage: response.data.last_page || 1
        };
      }
    } catch (err) {
      error.value = err;
    }
  };

  const setupPusher = () => {
    pusher = new Pusher('c493e35de663a696d88e', {
      cluster: 'eu',
      encrypted: true,
    });

    generalChannel = pusher.subscribe('games');
    generalChannel.bind('game.updated', async () => {
      await fetchGames();
    });
  };

  onMounted(() => {
    fetchGames(); // Fetch games initially
    setupPusher();
  });

  onUnmounted(() => {
    if (generalChannel) {
      generalChannel.unbind_all();
      pusher.unsubscribe('games');
    }
  });

  return {
    games,
    gameScores,
    scoresMetadata, // Expose pagination metadata
    fetchGames,
    fetchGameScores,
    error,
  };
}