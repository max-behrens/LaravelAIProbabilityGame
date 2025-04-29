// useGames.js
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Pusher from 'pusher-js';

export function useGames() {
  const games = ref([]);
  const gameScores = ref([]); // Make it reactive and local
  const error = ref(null);
  let pusher = null;
  let generalChannel = null;

  const fetchGames = async () => {
    try {
      const response = await axios.get('/dashboard/games');
      games.value = response.data;
    } catch (err) {
      error.value = err;
    }
  };

  const fetchGameScores = async (gameId) => {
    try {
      const response = await axios.get(`/games/${gameId}/scores`);
      gameScores.value = response.data;
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
    fetchGames();
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
    gameScores,        // Expose this to the component
    fetchGames,
    fetchGameScores,
    error,
  };
}
