<script setup>
import { onMounted, ref, onUnmounted } from 'vue';
import ReactComponent from '@/Components/ReactComponent';

// Reference to the container where React will mount
const reactContainer = ref(null);
let reactRoot = null;

onMounted(() => {
  if (reactContainer.value && window.React && window.createReactRoot) {
    try {
      console.log('Mounting React component');
      
      // Create a React root and render the component
      reactRoot = window.createReactRoot(reactContainer.value);
      reactRoot.render(
        window.React.createElement(ReactComponent)
      );
      
      console.log('React component rendered');
    } catch (error) {
      console.error('Error mounting React component:', error);
    }
  } else {
    console.error('React container or React libraries not available');
  }
});

// Clean up React component when unmounted
onUnmounted(() => {
  if (reactRoot) {
    reactRoot.unmount();
  }
});
</script>

<template>
  <div ref="reactContainer" class="react-container mt-6"></div>
</template>