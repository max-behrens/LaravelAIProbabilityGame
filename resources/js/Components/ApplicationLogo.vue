<script setup>
  import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
  import { faLaravel } from '@fortawesome/free-brands-svg-icons';
  import { library } from '@fortawesome/fontawesome-svg-core';


//   import { ref } from 'vue';

// const laravel_image = ref('/images/laravel-2.png');

  // Add the Laravel icon to the Font Awesome library
  library.add(faLaravel);
</script>

<template>
        <!-- <img :src="laravel_image" /> -->
  <font-awesome-icon :icon="['fab', 'laravel']" style="color: white;" />
</template>


