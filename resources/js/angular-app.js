import 'zone.js';
import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';
import { AppModule } from './angular/angular.module';

// Wait for the DOM to be fully available
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAngular);
} else {
    initAngular();
}

function initAngular() {
    const angularApp = document.getElementById('angular-app');
    if (!angularApp) {
        console.warn('Angular app element not found, deferring initialization');
        // Check every 100ms if the element is available (it might be added after React rendering)
        setTimeout(() => {
            const retryAngularApp = document.getElementById('angular-app');
            if (retryAngularApp) {
                bootstrapAngular();
            }
        }, 100);
        return;
    }
    bootstrapAngular();
}

function bootstrapAngular() {
    console.log('Bootstrapping Angular application');
    platformBrowserDynamic().bootstrapModule(AppModule)
        .catch(err => console.error('Angular bootstrap error:', err));
}

// Export a function that can be called to manually bootstrap Angular if needed
export function initializeAngular() {
    initAngular();
}