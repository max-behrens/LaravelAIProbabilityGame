// resources/js/Composables/useGames.js
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
    } catch (err) {
      console.error('Error fetching games:', err);
      error.value = err;
    }
  };

  const setupPusher = () => {
    pusher = new Pusher('c493e35de663a696d88e', {
      cluster: 'eu',
      encrypted: true,
    });

    generalChannel = pusher.subscribe('games');
    generalChannel.bind('game.updated', (data) => {
      console.log('Game updated:', data);

      const gameIndex = games.value.findIndex(g => g.id === data.game_id);
      if (gameIndex !== -1) {
        games.value[gameIndex].players_count = data.players_count;
        games.value = [...games.value]; // Force reactivity
      }
    });
  };

  onMounted(async () => {
    await fetchGames();
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
