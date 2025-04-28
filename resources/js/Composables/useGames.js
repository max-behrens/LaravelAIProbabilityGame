import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Pusher from 'pusher-js';

export function useGames() {
  const games = ref([]);
  const error = ref(null);
  let pusher = null;
  let generalChannel = null;

  const fetchGames = async () => {
    try {
      const response = await axios.get('/dashboard/games');
      games.value = response.data;
      return response.data;
    } catch (err) {
      console.error('Error fetching games:', err);
      error.value = err;
      return [];
    }
  };

  const setupPusher = () => {
    pusher = new Pusher('c493e35de663a696d88e', {
      cluster: 'eu',
      encrypted: true,
    });

    generalChannel = pusher.subscribe('games');
    generalChannel.bind('game.updated', async (data) => {
      console.log('Game updated:', data);
      // Refresh all games data on update
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
    error,
    fetchGames,
  };
}