// /sw.js (SOPORTE PWA)
const CACHE = "cgl-v1";

const ASSETS = [
  "/CGL/assets/css/app.css",
  "/CGL/assets/js/app.js",
  "/CGL/manifest.json",
  "/CGL/assets/img/logo.png",
  "/CGL/assets/img/icon-192.png",
  "/CGL/assets/img/icon-512.png"
];

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)));
  self.skipWaiting();
});

self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.map(k => (k !== CACHE ? caches.delete(k) : null)))
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (e) => {
  const req = e.request;
  const url = new URL(req.url);

  // Solo cache dentro del scope del proyecto
  if (!url.pathname.startsWith("/CGL/")) return;

  // Network-first para PHP (datos frescos)
  if (url.pathname.endsWith(".php") || url.pathname.includes("ajax_")) {
    e.respondWith(
      fetch(req).catch(() => caches.match("/CGL/dashboard.php"))
    );
    return;
  }

  // Cache-first para estáticos
  e.respondWith(
    caches.match(req).then(cached => cached || fetch(req).then(res => {
      const copy = res.clone();
      caches.open(CACHE).then(c => c.put(req, copy));
      return res;
    }))
  );
});
