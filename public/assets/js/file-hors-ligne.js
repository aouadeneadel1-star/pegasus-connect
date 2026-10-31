// File des pointages, passages et signalements enregistrés sans réseau (IndexedDB).
// Chaque événement porte son UUID : un renvoi ne crée jamais de doublon côté serveur.
import { envoyer, ErreurReseau } from './api.js';
import { toast } from './ui.js';

const BASE = 'pegasus';
const MAGASIN = 'file';
const MAXIMUM = 200;
const ALERTE_ANCIENNETE_MS = 6 * 3600 * 1000;
const INTERVALLE_MS = 60 * 1000;

let base = null;
let synchronisationEnCours = false;

function ouvrir() {
  if (base) {
    return base;
  }
  base = new Promise((resoudre, rejeter) => {
    const demande = indexedDB.open(BASE, 1);
    demande.onupgradeneeded = () => {
      demande.result.createObjectStore(MAGASIN, { keyPath: 'uuid' });
    };
    demande.onsuccess = () => resoudre(demande.result);
    demande.onerror = () => rejeter(demande.error);
  });

  return base;
}

async function transaction(mode, operation) {
  const db = await ouvrir();

  return new Promise((resoudre, rejeter) => {
    const tx = db.transaction(MAGASIN, mode);
    const resultat = operation(tx.objectStore(MAGASIN));
    tx.oncomplete = () => resoudre(resultat?.result ?? resultat);
    tx.onerror = () => rejeter(tx.error);
  });
}

export async function lister() {
  const db = await ouvrir();

  return new Promise((resoudre, rejeter) => {
    const demande = db.transaction(MAGASIN, 'readonly').objectStore(MAGASIN).getAll();
    demande.onsuccess = () => resoudre(demande.result.sort((a, b) => a.cree_le - b.cree_le));
    demande.onerror = () => rejeter(demande.error);
  });
}

export async function ajouter(type, donnees) {
  const evenements = await lister();
  if (evenements.length >= MAXIMUM) {
    throw new Error('La file hors ligne est pleine. Retrouvez du réseau avant de continuer.');
  }
  await transaction('readwrite', (magasin) => magasin.put({ uuid: donnees.uuid, type, donnees, cree_le: Date.now() }));
  await actualiserBandeau();
}

async function retirer(uuids) {
  await transaction('readwrite', (magasin) => uuids.forEach((uuid) => magasin.delete(uuid)));
}

async function actualiserBandeau() {
  const bandeau = document.querySelector('[data-file-hors-ligne]');
  if (!bandeau) {
    return;
  }
  const evenements = await lister();
  bandeau.hidden = evenements.length === 0;
  bandeau.querySelector('[data-file-compteur]').textContent = String(evenements.length);
  bandeau.classList.toggle('alert-danger', evenements.length > 0 && Date.now() - evenements[0].cree_le > ALERTE_ANCIENNETE_MS);
}

export async function synchroniser() {
  if (synchronisationEnCours || !navigator.onLine) {
    return;
  }
  synchronisationEnCours = true;
  try {
    const evenements = await lister();
    if (evenements.length === 0) {
      return;
    }
    const reponse = await envoyer('/api/v1/sync', {
      evenements: evenements.map(({ type, donnees }) => ({ type, donnees })),
    });

    const traites = [];
    for (const resultat of reponse.resultats ?? []) {
      if (resultat.ok || resultat.definitif) {
        traites.push(resultat.uuid);
      }
      if (!resultat.ok && resultat.definitif) {
        toast(`Un enregistrement hors ligne a été refusé : ${resultat.erreur}`, 'erreur', 10000);
      }
    }
    await retirer(traites);
    if (traites.length > 0) {
      toast(`${traites.length} enregistrement(s) transmis.`, 'succes');
    }
  } catch (erreur) {
    if (!(erreur instanceof ErreurReseau)) {
      toast(erreur.message, 'erreur');
    }
  } finally {
    synchronisationEnCours = false;
    await actualiserBandeau();
  }
}

export function demarrerSynchronisation() {
  window.addEventListener('online', synchroniser);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      synchroniser();
    }
  });
  setInterval(async () => {
    if ((await lister()).length > 0) {
      synchroniser();
    }
  }, INTERVALLE_MS);
  actualiserBandeau();
  synchroniser();
}
