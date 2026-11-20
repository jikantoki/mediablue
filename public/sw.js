const CACHE_NAME = 'mediablue-v2.1.0'

const PRECACHE_ASSETS = [
  '/',
  '/index.php',
  '/guide.php',
  '/manifest.json',
  '/css/style.css',
  '/js/i18n.js',
  '/js/app.js',
  '/js/player.js',
  '/js/bluetooth.js',
  '/assets/icon.svg',
  '/assets/icon-192.png',
  '/assets/icon-512.png',
  '/assets/icon-maskable-192.png',
  '/assets/icon-maskable-512.png',
]

// 1. インストール時: キャッシュの初期化
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(PRECACHE_ASSETS)
      })
      .then(() => {
        return self.skipWaiting()
      }),
  )
})

// 2. アクティベーション時: 古いキャッシュのクリーンアップ
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((name) => {
            if (name !== CACHE_NAME) {
              return caches.delete(name)
            }
          }),
        )
      })
      .then(() => {
        return self.clients.claim()
      }),
  )
})

// 3. フェッチ時: リクエストハンドリング
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url)

  // APIリクエストはキャッシュしない（常にネットワーク直通）
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request).catch(() => {
        return new Response(
          JSON.stringify({
            success: false,
            error: 'Network connection lost. Offline mode active.',
          }),
          {
            headers: { 'Content-Type': 'application/json' },
          },
        )
      }),
    )
    return
  }

  // 静的ファイルやUIシェル: Network First, falling back to cache
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (
          networkResponse &&
          networkResponse.status === 200 &&
          event.request.method === 'GET'
        ) {
          const clonedResponse = networkResponse.clone()
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, clonedResponse)
          })
        }
        return networkResponse
      })
      .catch(() => {
        // オフライン時はキャッシュから返す
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse
          }
          if (event.request.mode === 'navigate') {
            return caches.match('/')
          }
        })
      }),
  )
})
