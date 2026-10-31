// Borne de pointage : enrôlement, QR dynamique signé localement, lecture des badges NFC,
// file hors ligne. La clé de la borne est conservée dans IndexedDB sous forme non exportable.
import { dessiner } from './qr-encoder.js';

const FENETRE_S = 60;
const BATTEMENT_MS = 5 * 60 * 1000;
const SYNCHRO_MS = 60 * 1000;
const CONSERVATION_MS = 24 * 3600 * 1000;
const DELAI_FRAPPE_MS = 150;

// Stockage local --------------------------------------------------------------------------------

function base() {
  return new Promise((resoudre, rejeter) => {
    const demande = indexedDB.open('pegasus-borne', 1);
    demande.onupgradeneeded = () => {
      demande.result.createObjectStore('cles');
      demande.result.createObjectStore('file', { keyPath: 'uuid' });
    };
    demande.onsuccess = () => resoudre(demande.result);
    demande.onerror = () => rejeter(demande.error);
  });
}

async function stocker(magasin, valeur, cle = undefined) {
  const db = await base();
  return new Promise((resoudre, rejeter) => {
    const tx = db.transaction(magasin, 'readwrite');
    tx.objectStore(magasin).put(valeur, cle);
    tx.oncomplete = () => resoudre();
    tx.onerror = () => rejeter(tx.error);
  });
}

async function lire(magasin, cle) {
  const db = await base();
  return new Promise((resoudre, rejeter) => {
    const demande = db.transaction(magasin, 'readonly').objectStore(magasin).get(cle);
    demande.onsuccess = () => resoudre(demande.result);
    demande.onerror = () => rejeter(demande.error);
  });
}

async function tout(magasin) {
  const db = await base();
  return new Promise((resoudre, rejeter) => {
    const demande = db.transaction(magasin, 'readonly').objectStore(magasin).getAll();
    demande.onsuccess = () => resoudre(demande.result);
    demande.onerror = () => rejeter(demande.error);
  });
}

async function supprimer(magasin, cles) {
  const db = await base();
  return new Promise((resoudre, rejeter) => {
    const tx = db.transaction(magasin, 'readwrite');
    cles.forEach((cle) => tx.objectStore(magasin).delete(cle));
    tx.oncomplete = () => resoudre();
    tx.onerror = () => rejeter(tx.error);
  });
}

const base64url = (octets) => btoa(String.fromCharCode(...octets)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

function uuid() {
  if (crypto.randomUUID) {
    return crypto.randomUUID();
  }
  const o = crypto.getRandomValues(new Uint8Array(16));
  o[6] = (o[6] & 0x0f) | 0x40;
  o[8] = (o[8] & 0x3f) | 0x80;
  const h = [...o].map((x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

async function poster(url, corps) {
  const controleur = new AbortController();
  const minuterie = setTimeout(() => controleur.abort(), 10000);
  try {
    const reponse = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(corps),
      credentials: 'same-origin',
      signal: controleur.signal,
    });
    const donnees = await reponse.json().catch(() => ({}));
    if (reponse.status >= 500) {
      throw new TypeError('Serveur indisponible');
    }
    return { statut: reponse.status, donnees };
  } finally {
    clearTimeout(minuterie);
  }
}

// Enrôlement -----------------------------------------------------------------------------------

const ecranEnrolement = document.querySelector('[data-enrolement]');
if (ecranEnrolement) {
  const formulaire = ecranEnrolement.querySelector('[data-formulaire-enrolement]');
  const erreur = formulaire.querySelector('[data-erreur]');
  const code = ecranEnrolement.dataset.enrolement;

  formulaire.addEventListener('submit', async (evenement) => {
    evenement.preventDefault();
    erreur.hidden = true;
    try {
      const { statut, donnees } = await poster(`/borne/${encodeURIComponent(code)}/enrolement`, { code: formulaire.elements.code.value });
      if (statut !== 200) {
        throw new Error(donnees.erreur ?? 'Enrôlement refusé.');
      }
      const brute = Uint8Array.from(atob(donnees.cle), (c) => c.charCodeAt(0));
      const cle = await crypto.subtle.importKey('raw', brute, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
      await stocker('cles', cle, `borne:${donnees.borne_id}:v${donnees.cle_version}`);
      window.location.reload();
    } catch (exception) {
      erreur.textContent = exception instanceof TypeError ? 'Pas de réseau : l\'enrôlement demande une connexion.' : exception.message;
      erreur.hidden = false;
    }
  });
}

// Écran de pointage ----------------------------------------------------------------------------

const ecran = document.querySelector('[data-borne]');
if (ecran) {
  demarrer(JSON.parse(ecran.dataset.borne));
}

async function demarrer(config) {
  const canvas = ecran.querySelector('[data-qr]');
  const horloge = ecran.querySelector('[data-horloge]');
  const decompte = ecran.querySelector('[data-decompte]');
  const etatReseau = ecran.querySelector('[data-etat-reseau]');
  const etatLecteur = ecran.querySelector('[data-etat-lecteur]');
  const etatFile = ecran.querySelector('[data-etat-file]');
  const urlBorne = `/borne/${encodeURIComponent(config.code)}`;

  let cle = await lire('cles', `borne:${config.borne}:v${config.cleVersion}`);
  let decalage = 0;
  let fenetreAffichee = null;
  let lecteurOk = null;

  const maintenant = () => Date.now() + decalage;

  function reseau(enLigne) {
    etatReseau.textContent = enLigne ? 'En ligne' : 'Hors ligne : badges conservés';
    etatReseau.classList.toggle('alerte', !enLigne);
  }

  if (!cle) {
    canvas.hidden = true;
    ecran.querySelector('.borne-consigne').textContent = 'Clé de la borne absente : un nouvel enrôlement est nécessaire.';
    return;
  }

  async function signer(charge) {
    const signature = new Uint8Array(await crypto.subtle.sign('HMAC', cle, new TextEncoder().encode(charge)));
    return base64url(signature.slice(0, 16));
  }

  async function rafraichir() {
    const instant = maintenant();
    const date = new Date(instant);
    horloge.textContent = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Paris' });

    const fenetre = Math.floor(instant / 1000 / FENETRE_S);
    const restant = FENETRE_S - Math.floor((instant / 1000) % FENETRE_S);
    decompte.style.width = `${(restant / FENETRE_S) * 100}%`;

    if (fenetre !== fenetreAffichee) {
      fenetreAffichee = fenetre;
      const charge = `D.${config.site}.${config.borne}.${fenetre}`;
      dessiner(canvas, `PGS1.${charge}.${await signer(charge)}`);
    }
  }

  async function battement() {
    try {
      const avant = Date.now();
      const { statut, donnees } = await poster(`${urlBorne}/battement`, { lecteur_nfc_ok: lecteurOk });
      if (statut === 401) {
        canvas.hidden = true;
        ecran.querySelector('.borne-consigne').textContent = 'Borne révoquée ou non reconnue. Contactez l\'administrateur.';
        return;
      }
      const aller = (Date.now() - avant) / 2;
      decalage = donnees.heure_serveur + aller - Date.now();
      if (donnees.cle_version !== config.cleVersion) {
        ecran.querySelector('.borne-consigne').textContent = 'La clé de cette borne a changé : un nouvel enrôlement est nécessaire.';
      }
      association(donnees.association);
      reseau(true);
      synchroniser();
    } catch {
      reseau(false);
    }
  }

  function association(enAttente) {
    const zone = ecran.querySelector('.borne-consigne-badge');
    if (!zone) {
      return;
    }
    zone.classList.toggle('association', Boolean(enAttente));
    zone.lastChild.textContent = enAttente ? ` Présentez la carte à associer (${enAttente.numero})` : ' ou présentez votre badge';
  }

  await rafraichir();
  setInterval(rafraichir, 1000);
  battement();
  setInterval(battement, BATTEMENT_MS);
  window.addEventListener('online', battement);
  window.addEventListener('offline', () => reseau(false));

  // L'écran reste allumé : la tablette est une borne fixe.
  try {
    await navigator.wakeLock?.request('screen');
  } catch {
    // Refusé par le navigateur : à régler dans les paramètres de la tablette.
  }

  if (config.badge) {
    activerBadges(config, urlBorne, cle, (ok) => {
      lecteurOk = ok;
      if (etatLecteur) {
        etatLecteur.textContent = ok ? 'Lecteur de badge prêt' : 'Lecteur de badge indisponible';
        etatLecteur.classList.toggle('alerte', !ok);
      }
    }, etatFile);
  }

  // Synchronisation de la file hors ligne des badgeages.
  async function synchroniser() {
    const evenements = (await tout('file')).filter((e) => Date.now() - e.cree_le < CONSERVATION_MS);
    if (evenements.length === 0) {
      etatFile.hidden = true;
      return;
    }
    try {
      const charges = await Promise.all(evenements.map(async (e) => ({
        uuid: e.uuid,
        uid: e.uid,
        horodatage_borne: e.horodatage_borne,
        signature: e.signature,
        pin: e.pin ? await dechiffrerPin(e.pin) : null,
      })));
      const { statut, donnees } = await poster(`${urlBorne}/badge/sync`, { evenements: charges });
      if (statut !== 200) {
        return;
      }
      const traites = (donnees.resultats ?? []).filter((r) => r.ok || r.definitif).map((r) => r.uuid);
      await supprimer('file', traites);
    } catch {
      // Toujours hors ligne : nouvel essai plus tard.
    } finally {
      const restants = (await tout('file')).length;
      etatFile.hidden = restants === 0;
      etatFile.textContent = `${restants} badgeage(s) en attente d'envoi`;
    }
  }
  setInterval(synchroniser, SYNCHRO_MS);
  window.borneSynchroniser = synchroniser;
}

// Chiffrement des codes PIN en attente : clé AES non exportable, propre à la tablette.

async function clePin() {
  let cle = await lire('cles', 'pin');
  if (!cle) {
    cle = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
    await stocker('cles', cle, 'pin');
  }
  return cle;
}

async function chiffrerPin(pin) {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const chiffre = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await clePin(), new TextEncoder().encode(pin));
  return { iv, chiffre };
}

async function dechiffrerPin({ iv, chiffre }) {
  const clair = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, await clePin(), chiffre);
  return new TextDecoder().decode(clair);
}

// Lecture des badges ---------------------------------------------------------------------------

function activerBadges(config, urlBorne, cle, signalerLecteur, etatFile) {
  const voile = document.querySelector('[data-pin]');
  const points = [...voile.querySelectorAll('[data-pin-points] i')];
  const resultat = document.querySelector('[data-resultat]');
  let enCours = null;
  let saisie = '';

  function afficherResultat(type, titre, heure = '', message = '') {
    voile.hidden = true;
    resultat.className = `borne-voile borne-resultat ${type}`;
    resultat.querySelector('[data-resultat-icone]').innerHTML = document.querySelector(`[data-icone-${type === 'succes' ? 'ok' : type}]`)?.innerHTML ?? '';
    resultat.querySelector('[data-resultat-titre]').textContent = titre;
    resultat.querySelector('[data-resultat-heure]').textContent = heure;
    resultat.querySelector('[data-resultat-message]').textContent = message;
    resultat.hidden = false;
    setTimeout(() => {
      resultat.hidden = true;
      enCours = null;
      capturer();
    }, config.confirmation * 1000);
  }

  function demanderPin(nom) {
    saisie = '';
    points.forEach((p) => p.classList.remove('rempli'));
    voile.querySelector('[data-pin-nom]').textContent = nom ?? '';
    voile.hidden = false;
    clearTimeout(enCours?.expiration);
    enCours.expiration = setTimeout(() => {
      voile.hidden = true;
      enCours = null;
      capturer();
    }, 30000);
  }

  async function envoyer(pin = null) {
    const corps = { uid: enCours.uid, uuid: enCours.uuid, pin };
    try {
      const { statut, donnees } = await poster(`${urlBorne}/badge`, corps);
      if (statut === 401) {
        afficherResultat('erreur', 'Borne non reconnue', '', 'Contactez l\'administrateur.');
      } else if (donnees.etat === 'pin') {
        demanderPin(donnees.prenom);
      } else if (donnees.etat === 'enregistre') {
        afficherResultat('succes', `${donnees.type === 'arrivee' ? 'Arrivée' : 'Départ'} enregistré${donnees.type === 'arrivee' ? 'e' : ''}`, donnees.heure ?? '', donnees.prenom ?? '');
      } else if (donnees.etat === 'association') {
        afficherResultat('succes', 'Badge associé', '', donnees.message);
      } else {
        afficherResultat('erreur', 'Badgeage refusé', '', donnees.erreur ?? '');
      }
    } catch {
      await horsLigne(pin);
    }
  }

  async function horsLigne(pin) {
    if (config.pin && pin === null) {
      demanderPin('');
      enCours.horsLigne = true;
      return;
    }
    const horodatage = new Date().toISOString();
    const signatureBrute = new Uint8Array(await crypto.subtle.sign('HMAC', cle, new TextEncoder().encode(`${enCours.uid}|${enCours.uuid}|${horodatage}`)));
    await stocker('file', {
      uuid: enCours.uuid,
      uid: enCours.uid,
      horodatage_borne: horodatage,
      signature: base64url(signatureBrute.slice(0, 16)),
      pin: pin ? await chiffrerPin(pin) : null,
      cree_le: Date.now(),
    });
    etatFile.hidden = false;
    etatFile.textContent = 'Badgeage(s) en attente d\'envoi';
    afficherResultat('attente', 'Badge enregistré', new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }), 'Il sera vérifié au retour du réseau.');
  }

  function lu(uidBrut) {
    if (enCours) {
      return;
    }
    let uid = uidBrut.trim();
    // Certains lecteurs USB transmettent l'UID en décimal (10 chiffres) : conversion en hexadécimal.
    if (/^\d{10}$/.test(uid)) {
      uid = Number(uid).toString(16).padStart(8, '0');
    }
    uid = uid.replace(/[^0-9a-f]/gi, '').toUpperCase();
    if (![8, 14, 20].includes(uid.length)) {
      return;
    }
    enCours = { uid, uuid: uuid() };
    if (!navigator.onLine) {
      horsLigne(null);
      return;
    }
    envoyer(null);
  }

  voile.addEventListener('click', (evenement) => {
    const touche = evenement.target.closest('[data-touche]')?.dataset.touche;
    if (!touche || !enCours) {
      return;
    }
    if (touche === 'annuler') {
      voile.hidden = true;
      clearTimeout(enCours.expiration);
      enCours = null;
      capturer();
      return;
    }
    if (touche === 'effacer') {
      saisie = saisie.slice(0, -1);
    } else if (saisie.length < 4) {
      saisie += touche;
    }
    points.forEach((p, i) => p.classList.toggle('rempli', i < saisie.length));
    if (saisie.length === 4) {
      clearTimeout(enCours.expiration);
      const pin = saisie;
      saisie = '';
      if (enCours.horsLigne) {
        horsLigne(pin);
      } else {
        envoyer(pin);
      }
    }
  });

  // Lecteur USB en émulation clavier : les caractères arrivent en rafale, suivis d'Entrée.
  const capture = document.querySelector('[data-capture-badge]');
  let tampon = '';
  let derniere = 0;
  function capturer() {
    if (capture && config.lecteur === 'usb' && voile.hidden) {
      capture.focus({ preventScroll: true });
    }
  }
  if (capture && config.lecteur === 'usb') {
    capture.addEventListener('keydown', (evenement) => {
      const instant = performance.now();
      if (instant - derniere > DELAI_FRAPPE_MS) {
        tampon = '';
      }
      derniere = instant;
      if (evenement.key === 'Enter') {
        evenement.preventDefault();
        if (tampon.length >= 8) {
          lu(tampon);
        }
        tampon = '';
      } else if (evenement.key.length === 1) {
        tampon += evenement.key;
      }
    });
    capture.addEventListener('input', () => { capture.value = ''; });
    capture.addEventListener('blur', () => setTimeout(capturer, 300));
    document.addEventListener('click', capturer);
    capturer();
    signalerLecteur(true);
  }

  // NFC intégré (Chrome Android) : l'écoute exige un premier geste de l'utilisateur.
  if (config.lecteur === 'web_nfc') {
    if (!('NDEFReader' in window)) {
      signalerLecteur(false);
      return;
    }
    const lancer = async () => {
      try {
        const lecteur = new window.NDEFReader();
        await lecteur.scan();
        lecteur.addEventListener('reading', ({ serialNumber }) => lu(serialNumber ?? ''));
        signalerLecteur(true);
      } catch {
        signalerLecteur(false);
      }
    };
    document.addEventListener('pointerdown', lancer, { once: true });
    signalerLecteur(false);
  }
}
