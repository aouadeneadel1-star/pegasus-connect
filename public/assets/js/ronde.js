// Déroulement d'une ronde : scan des points, progression, clôture. Fonctionne hors ligne
// une fois la ronde démarrée : les passages sont mis en file et transmis plus tard.
import { envoyer, idAppareil, uuid, ErreurApi, ErreurReseau } from './api.js';
import { ajouter } from './file-hors-ligne.js';
import { Scanner, position, formatValide } from './scanner.js';
import { confirmer, toast, vibrer } from './ui.js';

const racine = document.querySelector('[data-ronde]');
const rondeId = Number(racine.dataset.ronde);
const geolocalisationAutorisee = racine.dataset.geolocalisation === '1';
const zoneScanner = racine.querySelector('[data-scanner]');
const boutonScanner = racine.querySelector('[data-scanner-point]');
const formulaireCloture = racine.querySelector('[data-cloture]');
let scanner = null;
let occupe = false;

function marquerControle(pointId, heure) {
  const ligne = racine.querySelector(`[data-point="${pointId}"]`);
  if (!ligne) {
    return;
  }
  const icone = ligne.querySelector('.list-icon');
  icone.classList.add('success');
  icone.innerHTML = '<svg class="icon" aria-hidden="true"><use href="/assets/img/icones.svg#i-check"></use></svg>';
  ligne.querySelector('[data-heure]').textContent = heure ?? '';

  const points = [...racine.querySelectorAll('[data-point]')];
  const faits = points.filter((p) => p.querySelector('.list-icon.success')).length;
  racine.querySelector('[data-progression]').textContent = `${faits} / ${points.length}`;
  [...racine.querySelectorAll('[data-segments] i')].forEach((segment, index) => {
    segment.classList.toggle('fait', Boolean(points[index]?.querySelector('.list-icon.success')));
  });
}

async function scanne(texte) {
  if (occupe) {
    return;
  }
  const message = zoneScanner.querySelector('[data-scanner-message]');
  if (!formatValide(texte, ['R'])) {
    message.textContent = 'Ce QR code n\'est pas un point de ronde.';
    vibrer(200);
    return;
  }

  occupe = true;
  const donnees = { jeton: texte, uuid: uuid(), horodatage_appareil: new Date().toISOString(), appareil_uuid: idAppareil(), ronde_id: rondeId };
  Object.assign(donnees, await position(geolocalisationAutorisee));

  try {
    const resultat = await envoyer(`/api/v1/rondes/${rondeId}/passages`, donnees);
    vibrer(100);
    marquerControle(resultat.passage.point_id, resultat.passage.heure);
    message.textContent = resultat.idempotent ? `${resultat.passage.point ?? 'Point'} déjà contrôlé.` : `${resultat.passage.point} contrôlé.`;
    if (resultat.passage.statut === 'a_verifier') {
      toast('Passage enregistré, à vérifier par votre chef d\'équipe.', 'info');
    }
  } catch (erreur) {
    if (erreur instanceof ErreurReseau) {
      await ajouter('passage', donnees);
      vibrer(100);
      message.textContent = 'Passage enregistré hors ligne.';
      const pointId = Number(texte.split('.')[2]);
      marquerControle(pointId, new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }));
    } else {
      vibrer([200, 100, 200]);
      message.textContent = erreur instanceof ErreurApi ? erreur.message : 'Erreur inattendue.';
    }
  } finally {
    setTimeout(() => { occupe = false; }, 800);
  }
}

boutonScanner?.addEventListener('click', async () => {
  if (scanner?.actif) {
    await scanner.arreter();
    zoneScanner.hidden = true;
    boutonScanner.lastChild.textContent = 'Scanner un point';
    return;
  }
  zoneScanner.hidden = false;
  scanner = new Scanner({
    conteneur: zoneScanner,
    onLecture: scanne,
    onMessage: (texte) => { zoneScanner.querySelector('[data-scanner-message]').textContent = texte; },
  });
  try {
    await scanner.demarrer();
    boutonScanner.lastChild.textContent = 'Fermer la caméra';
  } catch {
    zoneScanner.hidden = true;
  }
});

formulaireCloture?.addEventListener('submit', async (evenement) => {
  evenement.preventDefault();
  const commentaire = formulaireCloture.elements.commentaire.value.trim();
  const restants = [...racine.querySelectorAll('[data-point]')].filter((p) => !p.querySelector('.list-icon.success')).length;
  if (restants > 0 && !(await confirmer(`${restants} point(s) non contrôlé(s). Clôturer quand même la ronde ?`))) {
    return;
  }
  try {
    await scanner?.arreter();
    await envoyer(`/api/v1/rondes/${rondeId}/cloture`, { commentaire });
    window.location.href = '/rondes';
  } catch (erreur) {
    toast(erreur instanceof ErreurReseau ? 'La clôture demande du réseau. Vos passages sont bien enregistrés.' : erreur.message, 'erreur');
  }
});

// Temps restant avant la durée maximale de la ronde.
const tempsRestant = racine.querySelector('[data-temps-restant]');
if (tempsRestant && racine.dataset.finPrevue) {
  const fin = new Date(racine.dataset.finPrevue).getTime();
  const actualiser = () => {
    const minutes = Math.round((fin - Date.now()) / 60000);
    tempsRestant.textContent = minutes > 0 ? `Temps restant : ${minutes} min` : 'Durée maximale dépassée : clôturez la ronde.';
    tempsRestant.classList.toggle('text-danger', minutes <= 0);
  };
  actualiser();
  setInterval(actualiser, 30000);
}
