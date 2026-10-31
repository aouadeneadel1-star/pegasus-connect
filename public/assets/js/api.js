// Appels à l'API JSON : jeton CSRF, délai maximal, distinction entre refus et panne réseau.

const DELAI_MS = 15000;

export class ErreurReseau extends Error {}

export class ErreurApi extends Error {
  constructor(message, statut, donnees) {
    super(message);
    this.statut = statut;
    this.donnees = donnees;
  }
}

function jetonCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function requete(methode, url, corps = undefined, options = {}) {
  const controleur = new AbortController();
  const minuterie = setTimeout(() => controleur.abort(), options.delai ?? DELAI_MS);
  const enTetes = { Accept: 'application/json', 'X-CSRF-Token': jetonCsrf() };
  let contenu;

  if (corps instanceof FormData) {
    contenu = corps;
  } else if (corps !== undefined) {
    enTetes['Content-Type'] = 'application/json';
    contenu = JSON.stringify(corps);
  }

  let reponse;
  try {
    reponse = await fetch(url, {
      method: methode,
      headers: enTetes,
      body: contenu,
      credentials: 'same-origin',
      signal: controleur.signal,
    });
  } catch {
    throw new ErreurReseau('Réseau indisponible');
  } finally {
    clearTimeout(minuterie);
  }

  let donnees = null;
  try {
    donnees = await reponse.json();
  } catch {
    // Réponse vide ou HTML (page d'erreur du serveur) : traitée selon le statut.
  }

  if (reponse.status === 401 && donnees?.reauthentification) {
    window.location.href = donnees.reauthentification;
    throw new ErreurApi('Authentification requise', 401, donnees);
  }
  if (reponse.status >= 500 || reponse.status === 0) {
    throw new ErreurReseau('Service momentanément indisponible');
  }
  if (!reponse.ok) {
    throw new ErreurApi(donnees?.erreur ?? 'Requête refusée', reponse.status, donnees);
  }

  return donnees;
}

export const obtenir = (url, options) => requete('GET', url, undefined, options);
export const envoyer = (url, corps, options) => requete('POST', url, corps, options);

export function uuid() {
  if (crypto.randomUUID) {
    return crypto.randomUUID();
  }
  const octets = crypto.getRandomValues(new Uint8Array(16));
  octets[6] = (octets[6] & 0x0f) | 0x40;
  octets[8] = (octets[8] & 0x3f) | 0x80;
  const hex = [...octets].map((o) => o.toString(16).padStart(2, '0')).join('');

  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

// Identifiant stable de l'appareil : il sert à repérer un téléphone prêté, pas à authentifier.
export function idAppareil() {
  try {
    let id = localStorage.getItem('pg-appareil');
    if (!id) {
      id = uuid();
      localStorage.setItem('pg-appareil', id);
    }
    return id;
  } catch {
    return null;
  }
}
