// Rafraîchissement périodique des zones de suivi (tableau de bord, pointages du jour).
// La page est rechargée en arrière-plan et seule la zone concernée est remplacée.
const zones = [...document.querySelectorAll('[data-rafraichir]')].filter((zone) => Number(zone.dataset.rafraichir) > 0);

if (zones.length > 0) {
  const intervalle = Math.min(...zones.map((zone) => Number(zone.dataset.rafraichir))) * 1000;

  setInterval(async () => {
    if (document.visibilityState !== 'visible' || document.querySelector('details[open], dialog[open]')) {
      return;
    }
    try {
      const reponse = await fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'rafraichir' } });
      if (!reponse.ok) {
        return;
      }
      const page = new DOMParser().parseFromString(await reponse.text(), 'text/html');
      const nouvelles = page.querySelectorAll('[data-rafraichir]');
      zones.forEach((zone, index) => {
        if (nouvelles[index]) {
          zone.replaceChildren(...nouvelles[index].childNodes);
        }
      });
    } catch {
      // Réseau momentanément indisponible : nouvel essai au prochain intervalle.
    }
  }, intervalle);
}
