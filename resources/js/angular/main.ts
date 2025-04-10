import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';
import { AppModule } from './angular.module';
import 'zone.js'; // Import Zone.js

// Bootstrap the Angular application
platformBrowserDynamic().bootstrapModule(AppModule)
  .catch(err => console.error(err));