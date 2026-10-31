// Écran « Pointer » : lecture du QR, géolocalisation, envoi, confirmation plein écran.
import { envoyer, idAppareil, uuid, ErreurApi, ErreurReseau } from './api.js';
import { ajouter } from './file-hors-ligne.js';
import { Scanner, position, formatValide } from './scanner.js';
import { formaterHeure, toast, vibrer } from './ui.js';

const racine = document.querySelector('[data-pointage]');
const confirmation = document.querySelector('[data-confirmation]');
const dialogueSite = document.querySelector('[data-changement-site]');
const message = racine.querySelector('[data-scanner-message]');
const geolocalisationAutorisee = racine.dataset.geolocalisation === '1';
let occupe = false;
let compteARebours = null;

const scanner = new Scanner({
  conteneur: racine.querySelector('[data-scanner]'),
  onLecture: (texte) => traiter(texte),
  onMessage: (texte) => { message.textContent = texte; },
});

function icone(nom) {
  const modele = document.querySelector(`[data-modele-icone-${nom}]`);
  confirmation.querySelector('[data-confirmation-icone]').innerHTML = modele ? modele.innerHTML : '';
}

function afficher({ classe = '', titre, heure = '', site = '', raisons = [], annulable = null, idPointage = null, icon = null }) {
  confirmation.className = `confirmation ${classe}`.trim();
  if (icon) {
    icone(icon);
  }
  confirmation.querySelector('[data-confirmation-titre]').textContent = titre;
  confirmation.querySelector('[data-confirmation-heure]').textContent = heure;
  confirmation.querySelector('[data-confirmation-site]').textContent = site;
  const liste = confirmation.querySelector('[data-confirmation-raisons]');
  liste.replaceChildren(...raisons.map((raison) => Object.assign(document.createElement('li'), { textContent: raison })));

  const boutonAnnuler = confirmation.querySelector('[data-annuler-pointage]');
  clearInterval(compteARebours);
  boutonAnnuler.hidden = !annulable;
  if (annulable) {
    const limite = new Date(annulable).getTime();
    const decompte = boutonAnnuler.querySelector('[data-decompte]');
    const actualiser = () => {
      const restant = Math.round((limite - Date.now()) / 1000);
      if (restant <= 0) {
        boutonAnnuler.hidden = true;
        clearInterval(compteARebours);
        return;
      }
      decompte.textContent = `(${restant} s)`;
    };
    actualiser();
    compteARebours = setInterval(actualiser, 1000);
    boutonAnnuler.onclick = () => annuler(idPointage);
  }

  confirmation.hidden = false;
  confirmation.focus();
}

async function traiter(texte, changerSite = false) {
  if (occupe) {
    return;
  }
  if (!formatValide(texte, ['D', 'S', 'R'])) {
    message.textContent = 'Ce QR code n\'est pas un code Pegasus.';
    vibrer(200);
    return;
  }
  if (texte.split('.')[1] === 'R') {
    message.textContent = 'C\'est le QR d\'un point de ronde : scannez celui de la borne ou du poste.';
    vibrer(200);
    return;
  }

  occupe = true;
  message.textContent = 'Enregistrement…';
  const geo = await position(geolocalisationAutorisee);
  const donnees = {
    jeton: texte,
    uuid: uuid(),
    horodatage_appareil: new Date().toISOString(),
    appareil_uuid: idAppareil(),
    changer_site: changerSite,
    ...geo,
  };

  try {
    const resultat = await envoyer('/api/v1/pointages', donnees);
    if (resultat.confirmation_requise) {
      occupe = false;
      demanderChangementSite(texte, resultat.message);
      return;
    }
    await scanner.arreter();
    vibrer(resultat.statut === 'a_verifier' ? [80, 80, 80] : 120);
    const type = resultat.type === 'arrivee' ? 'Arrivée' : 'Départ';
    afficher({
      classe: resultat.statut === 'a_verifier' ? 'a-verifier' : '',
      icon: resultat.statut === 'a_verifier' ? 'alerte' : null,
      titre: resultat.doublon ? `${type} déjà enregistrée` : (resultat.statut === 'a_verifier' ? `${type} enregistrée — à vérifier` : `${type} enregistrée`),
      heure: resultat.heure,
      site: resultat.site,
      raisons: resultat.raisons ?? [],
      annulable: resultat.idempotent ? null : resultat.annulable_jusqua,
      idPointage: resultat.id,
    });
  } catch (erreur) {
    if (erreur instanceof ErreurReseau) {
      try {
        await ajouter('pointage', donnees);
        await scanner.arreter();
        vibrer(120);
        afficher({
          classe: 'hors-ligne',
          icon: 'sync',
          titre: 'Enregistré hors ligne',
          heure: formaterHeure(donnees.horodatage_appareil),
          raisons: ['Le pointage sera transmis dès le retour du réseau.'],
        });
      } catch (erreurFile) {
        message.textContent = erreurFile.message;
      }
    } else {
      vibrer([200, 100, 200]);
      message.textContent = erreur instanceof ErreurApi ? erreur.message : 'Erreur inattendue.';
      toast(message.textContent, 'erreur');
    }
  } finally {
    occupe = false;
  }
}

function demanderChangementSite(texte, explication) {
  dialogueSite.querySelector('[data-changement-message]').textContent = explication;
  dialogueSite.returnValue = '';
  dialogueSite.addEventListener('close', () => {
    if (dialogueSite.returnValue === 'oui') {
      traiter(texte, true);
    } else {
      message.textContent = 'Pointage abandonné.';
    }
  }, { once: true });
  dialogueSite.showModal();
}

async function annuler(idPointage) {
  try {
    await envoyer(`/api/v1/pointages/${idPointage}/annuler`, { motif: 'Erreur de pointage signalée par l\'agent' });
    clearInterval(compteARebours);
    afficher({ classe: 'erreur', icon: 'erreur', titre: 'Pointage annulé', raisons: ['Vous pouvez pointer à nouveau.'] });
  } catch (erreur) {
    toast(erreur.message, 'erreur');
  }
}

const boutonTorche = racine.querySelector('[data-torche]');
const boutonRelancer = racine.querySelector('[data-relancer]');
let torcheAllumee = false;

boutonTorche.addEventListener('click', async () => {
  torcheAllumee = !torcheAllumee;
  await scanner.eclairer(torcheAllumee);
});
boutonRelancer.addEventListener('click', () => lancer());

async function lancer() {
  boutonRelancer.hidden = true;
  try {
    await scanner.demarrer();
    boutonTorche.hidden = !scanner.peutEclairer();
  } catch {
    boutonRelancer.hidden = false;
  }
}

document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') {
    scanner.arreter();
  } else if (confirmation.hidden) {
    lancer();
  }
});

lancer();
