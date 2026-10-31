// Comportements communs à toutes les pages : thème, confirmations, copie, file hors ligne.
import { confirmer, toast } from './ui.js';
import { envoyer } from './api.js';

// Bascule du thème clair / sombre, mémorisée sur l'appareil.
document.addEventListener('click', (evenement) => {
  const bouton = evenement.target.closest('[data-bascule-theme]');
  if (!bouton) {
    return;
  }
  const racine = document.documentElement;
  const sombre = racine.dataset.theme
    ? racine.dataset.theme === 'dark'
    : window.matchMedia('(prefers-color-scheme: dark)').matches;
  racine.dataset.theme = sombre ? 'light' : 'dark';
  try {
    localStorage.setItem('pg-theme', racine.dataset.theme);
  } catch {
    // Stockage indisponible : le choix vaut pour la page.
  }
});

// Déconnexion : les pages gardées pour le hors ligne sont effacées de l'appareil.
document.addEventListener('submit', (evenement) => {
  if (evenement.target.action?.endsWith('/deconnexion')) {
    navigator.serviceWorker?.controller?.postMessage('vider');
  }
});

// Formulaires sensibles : confirmation avant envoi.
document.addEventListener('submit', async (evenement) => {
  const formulaire = evenement.target;
  const message = formulaire.dataset?.confirmer;
  if (!message || formulaire.dataset.confirme === '1') {
    return;
  }
  evenement.preventDefault();
  const declencheur = evenement.submitter;
  if (await confirmer(message)) {
    formulaire.dataset.confirme = '1';
    formulaire.requestSubmit(declencheur ?? undefined);
    delete formulaire.dataset.confirme;
  }
});

// Une liste déroulante marquée data-soumettre envoie son formulaire dès qu'elle change.
document.addEventListener('change', (evenement) => {
  if (evenement.target.matches('[data-soumettre]')) {
    evenement.target.form?.submit();
  }
});

// Copie dans le presse-papiers.
document.addEventListener('click', async (evenement) => {
  const bouton = evenement.target.closest('[data-copier]');
  if (!bouton) {
    return;
  }
  const source = bouton.parentElement.querySelector('[data-copiable]');
  try {
    await navigator.clipboard.writeText(source.textContent.trim());
    toast('Copié.', 'succes', 2000);
  } catch {
    toast('Copie impossible : sélectionnez le texte manuellement.', 'erreur');
  }
});

// Révélation temporaire du numéro de sécurité sociale (fiche agent).
document.addEventListener('click', async (evenement) => {
  const bouton = evenement.target.closest('[data-reveler-nir]');
  if (!bouton) {
    return;
  }
  const zone = bouton.closest('[data-nir]');
  const valeur = zone.querySelector('[data-nir-valeur]');
  const masque = valeur.textContent;
  bouton.disabled = true;
  try {
    const reponse = await envoyer(bouton.dataset.revelerNir, {});
    valeur.textContent = reponse.nir;
    setTimeout(() => {
      valeur.textContent = masque;
      bouton.disabled = false;
    }, (reponse.duree ?? 30) * 1000);
  } catch (erreur) {
    bouton.disabled = false;
    if (erreur.statut !== 401) {
      toast(erreur.message, 'erreur');
    }
  }
});

// Association d'un badge en cours : la page se recharge quand la carte a été présentée.
const attente = document.querySelector('[data-attente-association]');
if (attente) {
  const url = attente.dataset.attenteAssociation;
  const minuterie = setInterval(async () => {
    try {
      const reponse = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const etat = await reponse.json();
      if (etat.associe || !etat.en_attente) {
        clearInterval(minuterie);
        window.location.reload();
      }
    } catch {
      // Nouvel essai au prochain intervalle.
    }
  }, 3000);
}

// Espace agent : Service Worker et file des enregistrements hors ligne.
if (document.querySelector('[data-file-hors-ligne]')) {
  import('./file-hors-ligne.js').then(({ demarrerSynchronisation }) => demarrerSynchronisation());
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
      // L'application reste utilisable en ligne sans Service Worker.
    });
  }
}
