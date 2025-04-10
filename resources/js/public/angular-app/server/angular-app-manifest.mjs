
export default {
  bootstrap: () => import('./main.server.mjs').then(m => m.default),
  inlineCriticalCss: false,
  baseHref: '/',
  locale: undefined,
  routes: [
  {
    "renderMode": 2,
    "route": "/"
  }
],
  entryPointToBrowserMapping: undefined,
  assets: {
    'index.csr.html': {size: 440, hash: 'ef7177fd9948790007186a06ca55b73cee86d308523a556380e9a382ba24c15b', text: () => import('./assets-chunks/index_csr_html.mjs').then(m => m.default)},
    'index.server.html': {size: 980, hash: 'c7128b35c0136e3f3fff84cb2e275ae727f194ee3c110cbfd86b3df9e633c254', text: () => import('./assets-chunks/index_server_html.mjs').then(m => m.default)},
    'index.html': {size: 659, hash: '4d8195abb5985b64d32eea7481650a8ea7d79b24bf95d8e57ce8ba40bd6b82bb', text: () => import('./assets-chunks/index_html.mjs').then(m => m.default)}
  },
};
