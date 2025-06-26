// Check if OneSignal is being loaded
self.importScripts('https://cdn.onesignal.com/sdks/OneSignalSDKWorker.js');

const CACHE_NAME = 'indoor-tasks-v1';
const OFFLINE_URL = '/wp-content/plugins/indoor-tasks/assets/pwa/offline.html';

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll([
        OFFLINE_URL,
        '/wp-content/plugins/indoor-tasks/assets/css/preloader.css',
        '/wp-content/plugins/indoor-tasks/assets/css/tk-indoor-base.css',
        '/wp-content/plugins/indoor-tasks/assets/css/tk-indoor-auth.css',
        '/wp-content/plugins/indoor-tasks/assets/js/preloader.js',
        '/wp-content/plugins/indoor-tasks/assets/js/tk-indoor-auth.js',
      ]);
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keyList) => {
      return Promise.all(
        keyList.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
});

self.addEventListener('fetch', function(event) {
  // Skip cross-origin requests
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          return caches.open(CACHE_NAME)
            .then((cache) => {
              return cache.match(OFFLINE_URL);
            });
        })
    );
  } else {
    event.respondWith(
      caches.match(event.request)
        .then((response) => {
          return response || fetch(event.request)
            .then((response) => {
              return caches.open(CACHE_NAME)
                .then((cache) => {
                  // Don't cache API calls or OneSignal requests
                  if (!event.request.url.includes('/wp-json/') && 
                      !event.request.url.includes('onesignal')) {
                    cache.put(event.request, response.clone());
                  }
                  return response;
                });
            });
        })
    );
  }
});

// Handle push notifications from OneSignal
self.addEventListener('push', function(event) {
  let data = {};
  if (event.data) {
    data = event.data.json();
  }

  const options = {
    body: data.body || 'New update from Indoor Tasks',
    icon: data.icon || '/wp-content/plugins/indoor-tasks/assets/image/verified.png',
    badge: '/wp-content/plugins/indoor-tasks/assets/image/verified.png',
    data: {
      url: data.url || '/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'Indoor Tasks', options)
  );
});

// Handle notification click
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  
  if (event.notification.data && event.notification.data.url) {
    event.waitUntil(
      clients.openWindow(event.notification.data.url)
    );
  } else {
    event.waitUntil(
      clients.openWindow('/')
    );
  }
});
