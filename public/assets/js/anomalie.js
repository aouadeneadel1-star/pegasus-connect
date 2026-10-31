// Signalement d'anomalie avec photos. Hors ligne, le texte est mis en file ; les photos,
// trop lourdes pour la file, sont à joindre plus tard par le contact.
import { envoyer, uuid, ErreurReseau } from './api.js';
import { ajouter } from './file-hors-ligne.js';
import { toast } from './ui.js';

const formulaire = document.querySelector('[data-anomalie]');
const apercu = formulaire?.querySelector('[data-apercu]');
const erreur = formulaire?.querySelector('[data-erreur]');
const identifiant = uuid();

formulaire?.elements['photos[]'].addEventListener('change', (evenement) => {
  const fichiers = [...evenement.target.files].slice(0, 5);
  apercu.replaceChildren(...fichiers.map((fichier) => {
    const image = document.createElement('img');
    image.alt = '';
    image.src = URL.createObjectURL(fichier);
    image.onload = () => URL.revokeObjectURL(image.src);
    return image;
  }));
});

formulaire?.addEventListener('submit', async (evenement) => {
  evenement.preventDefault();
  erreur.hidden = true;
  if (!formulaire.reportValidity()) {
    return;
  }
  const bouton = formulaire.querySelector('button[type="submit"]');
  bouton.disabled = true;

  const donnees = new FormData(formulaire);
  donnees.set('uuid', identifiant);

  try {
    await envoyer('/api/v1/anomalies', donnees, { delai: 60000 });
    toast('Signalement envoyé.', 'succes');
    window.location.href = donnees.get('ronde_id') ? `/rondes/${donnees.get('ronde_id')}` : '/';
  } catch (exception) {
    bouton.disabled = false;
    if (exception instanceof ErreurReseau) {
      const texte = Object.fromEntries([...donnees.entries()].filter(([cle]) => cle !== 'photos[]'));
      await ajouter('anomalie', texte);
      toast('Pas de réseau : le signalement sera transmis automatiquement, sans les photos.', 'info', 8000);
      window.location.href = '/';
      return;
    }
    erreur.textContent = exception.message;
    erreur.hidden = false;
  }
});
