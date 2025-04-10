import './bootstrap';
import '../css/app.css';

import { vue3Debounce } from 'vue-debounce';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/inertia-vue3';
import { InertiaProgress } from '@inertiajs/progress';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
// Import React and ReactDOM
import React from 'react';
import { createRoot } from 'react-dom/client';

// Make React available globally
window.React = React;
window.createReactRoot = createRoot;

// Log to confirm React is available
console.log('React version:', React.version);
console.log('createRoot available:', typeof createRoot === 'function');

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, app, props, plugin }) {
        return createApp({ render: () => h(app, props) })
            .directive('debounce', vue3Debounce({ lock: true }))
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
});

InertiaProgress.init({ color: '#4B5563' });
