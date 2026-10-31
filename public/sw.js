// Service Worker de l'espace agent : application disponible hors ligne, pages récentes en cache.
// La version est mise à jour par bin/deploy.sh : un nouveau déploiement vide les anciens caches.
const VERSION = 'dev';
const CACHE_STATIQUE = `pegasus-statique-${VERSION}`;
const CACHE_PAGES = 'pegasus-pages';

const STATIQUES = [
  '/hors-ligne',
  '/assets/css/tokens.css',
  '/assets/css/base.css',
  '/assets/css/components.css',
  '/assets/css/agent.css',
  '/assets/js/theme.js',
  '/assets/js/app.js',
  '/assets/js/api.js',
  '/assets/js/ui.js',
  '/assets/js/file-hors-ligne.js',
  '/assets/js/scanner.js',
  '/assets/js/pointage.js',
  '/assets/js/ronde.js',
  '/assets/js/accueil.js',
  '/assets/js/anomalie.js',
  '/assets/img/icones.svg',
  '/assets/img/ecusson.png',
  '/assets/fonts/inter-variable.woff2',
  '/assets/vendor/html5-qrcode-2.3.8.min.js',
  '/assets/icons/icon-192.png',
];

// Pages consultables hors ligne si elles ont déjà été ouvertes.
const PAGES = [/^\/$/, /^\/pointer$/, /^\/planning$/, /^\/rondes(\/\d+)?$/, /^\/anomalie$/, /^\/profil$/, /^\/historique$/];

self.addEventListener('install', (evenement) => {
  evenement.waitUntil(
    caches.open(CACHE_STATIQUE)
      .then((cache) => Promise.all(STATIQUES.map((url) => cache.add(new Request(url, { credentials: 'same-origin' })).catch(() => {}))))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (evenement) => {
  evenement.waitUntil(
    caches.keys()
      .then((cles) => Promise.all(cles.filter((cle) => cle.startsWith('pegasus-statique-') && cle !== CACHE_STATIQUE).map((cle) => caches.delete(cle))))
      .then(() => self.clients.claim()),
  );
});

// Déconnexion : les pages en cache contiennent des données personnelles, elles sont effacées.
self.addEventListener('message', (evenement) => {
  if (evenement.data === 'vider') {
    evenement.waitUntil(caches.delete(CACHE_PAGES));
  }
});

self.addEventListener('fetch', (evenement) => {
  const requete = evenement.request;
  const url = new URL(requete.url);
  if (requete.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/fichier/') || url.pathname.startsWith('/admin') || url.pathname.startsWith('/borne')) {
    return;
  }

  if (requete.mode === 'navigate') {
    evenement.respondWith(page(requete, url));
    return;
  }

  if (url.pathname.startsWith('/assets/')) {
    evenement.respondWith(statique(requete, evenement));
  }
});

async function page(requete, url) {
  const memorisable = PAGES.some((motif) => motif.test(url.pathname));
  try {
    const reponse = await fetch(requete);
    if (memorisable && reponse.ok && !reponse.redirected) {
      const cache = await caches.open(CACHE_PAGES);
      await cache.put(url.pathname, reponse.clone());
    }
    return reponse;
  } catch {
    const enCache = memorisable ? await caches.match(url.pathname, { cacheName: CACHE_PAGES }) : null;
    return enCache ?? (await caches.match('/hors-ligne')) ?? new Response('Hors ligne', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
  }
}

// Ressources statiques : réponse immédiate depuis le cache, mise à jour en arrière-plan.
async function statique(requete, evenement) {
  const cache = await caches.open(CACHE_STATIQUE);
  const enCache = await cache.match(requete, { ignoreSearch: true });
  const reseau = fetch(requete)
    .then((reponse) => {
      if (reponse.ok) {
        cache.put(requete, reponse.clone());
      }
      return reponse;
    })
    .catch(() => null);
  if (enCache) {
    evenement.waitUntil(reseau);
    return enCache;
  }
  return (await reseau) ?? new Response('', { status: 504 });
}
